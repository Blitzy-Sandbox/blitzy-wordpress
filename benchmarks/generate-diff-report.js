#!/usr/bin/env node

/**
 * Benchmark diff + KPI report generator (Feature F-011).
 *
 * Computes before/after deltas and statistical significance between the
 * baseline and optimized measurement runs produced by the benchmark harness,
 * evaluates the six mandated performance KPI targets, and emits the
 * machine-readable `benchmark-report.json` contract consumed by the downstream
 * dashboard (Rule 1) and executive-presentation (Rule 4) deliverables. A
 * secondary, human-readable Markdown diff (`benchmark-diff-report.md`) is also
 * written.
 *
 * This is the benchmark-harness analogue of the CI script
 * `tests/performance/compare-results.js`; it reuses the exact statistics and
 * formatting helpers exported by `tests/performance/utils.js`, so its medians,
 * deltas, and value formatting are identical to the CI comparison.
 *
 * Statistical significance: every per-suite metric and every KPI carries a
 * two-tailed significance verdict from Welch's unequal-variances two-sample
 * t-test over the raw baseline vs. optimized samples. The p-value is derived
 * from the Student's t distribution via a self-contained Lanczos log-gamma and
 * Lentz-evaluated regularized incomplete beta (Node built-ins only), reported
 * as `{ test, tStatistic, degreesOfFreedom, pValue, significant, alpha }` with
 * `alpha = 0.05`; it is `null` when a group has fewer than two samples (for
 * example when no baseline is present).
 *
 * Deterministic output: given identical input files the report is byte-for-byte
 * reproducible -- all statistics are rounded to a fixed precision and the
 * timestamp falls back to a fixed sentinel (see `resolveGeneratedAt`) rather
 * than the wall clock -- so committed representative artifacts preserve
 * `git diff --exit-code` semantics.
 *
 * Inputs (read from `WP_ARTIFACTS_PATH`, default `<cwd>/benchmarks/results`):
 *   - `before-performance-results.json` : baseline raw results.
 *   - `performance-results.json`        : optimized raw results.
 *
 * Both are `Array<{ file: string, title: string, results: Array<Record<string, number[]>> }>`.
 * When a declared input artifact is missing the generator FAILS LOUDLY (writes
 * an error to stderr and exits non-zero) rather than emitting a partial report,
 * so the committed report contract cannot silently regress to fabricated or
 * empty numbers. The documented baseline-optional mode -- where the report is
 * still emitted with `N/A`/`null` in place of absent baseline values -- remains
 * available by explicitly passing `--allow-missing-baseline` (or setting
 * `BENCHMARK_ALLOW_MISSING_BASELINE=1`).
 *
 * Outputs (written to `WP_ARTIFACTS_PATH`):
 *   - `benchmark-report.json`    : machine-readable KPI + per-suite report.
 *   - `benchmark-diff-report.md` : human-readable Markdown diff.
 *   - optional `argv[0]`         : an extra copy of the Markdown summary.
 *
 * Usage:
 *   node benchmarks/generate-diff-report.js [ summary-file ]
 *
 * This is a test/development-only tool; it never runs in production.
 */

/**
 * External dependencies.
 */
const { writeFileSync, mkdirSync } = require( 'node:fs' );
const { join } = require( 'node:path' );

// Resolve the artifacts directory before requiring utils. utils.js uses `??=`,
// so it will not overwrite a value that has already been set here.
process.env.WP_ARTIFACTS_PATH ??= join(
	process.cwd(),
	'benchmarks',
	'results'
);

/**
 * Internal dependencies.
 *
 * Reuse -- never duplicate -- the statistics and formatting helpers shared
 * with the CI comparison script so medians, deltas, and value formatting stay
 * identical to `tests/performance/compare-results.js`.
 */
const {
	parseFile,
	median,
	standardDeviation,
	medianAbsoluteDeviation,
	accumulateValues,
	formatValue,
	formatAsMarkdownTable,
} = require( '../tests/performance/utils' );

/**
 * Baseline raw-results file name.
 *
 * @type {string}
 */
const BASELINE_FILE = 'before-performance-results.json';

/**
 * Optimized raw-results file name.
 *
 * @type {string}
 */
const OPTIMIZED_FILE = 'performance-results.json';

/**
 * The six mandated performance KPI targets.
 *
 * Every target is a lower-is-better reduction: a KPI passes when the reduction
 * `( before - after ) / before * 100` is greater than or equal to its
 * threshold (a percentage). Front-end KPIs aggregate samples across the
 * `Homepage` and `Single Post` suites; admin KPIs use the `Admin` suites. The
 * `unit` is informational and matches what `formatValue` renders for each
 * metric (ms / KB / MB / raw count).
 *
 * @type {Array<{id: number, label: string, metric: string, scope: string, unit: string, threshold: number}>}
 */
const KPI_DEFINITIONS = [
	{
		id: 1,
		label: 'Front-end TTFB (uncached)',
		metric: 'timeToFirstByte',
		scope: 'front-end',
		unit: 'ms',
		threshold: 20,
	},
	{
		id: 2,
		label: 'Admin DOMContentLoaded',
		metric: 'domContentLoaded',
		scope: 'admin',
		unit: 'ms',
		threshold: 15,
	},
	{
		id: 3,
		label: 'Admin JS transfer size (gzipped)',
		metric: 'adminJsTransferSize',
		scope: 'admin',
		unit: 'KB',
		threshold: 30,
	},
	{
		id: 4,
		label: 'PHP memory per front-end request',
		metric: 'wpMemoryUsage',
		scope: 'front-end',
		unit: 'MB',
		threshold: 10,
	},
	{
		id: 5,
		label: 'DB queries per front-end page',
		metric: 'wpDbQueries',
		scope: 'front-end',
		unit: 'raw count',
		threshold: 15,
	},
	{
		id: 6,
		label: 'PHP files loaded per front-end request',
		metric: 'wpFilesLoaded',
		scope: 'front-end',
		unit: 'raw count',
		threshold: 30,
	},
];

/**
 * Canonical Server-Timing metric keys emitted by the test-only
 * `server-timing.php` must-use plugin. Recorded verbatim so the dashboard
 * deliverable can render all seven, independently of the KPI list.
 *
 * @type {string[]}
 */
const SERVER_TIMING_METRICS = [
	'wpBootstrap',
	'wpPlugins',
	'wpFilesLoaded',
	'wpCacheHits',
	'wpCacheMisses',
	'wpDbQueries',
	'wpMemoryUsage',
];

/**
 * Metrics excluded from delta math, matching `compare-results.js`. The
 * `wpExtObjCache` metric is a boolean-style flag (whether an external object
 * cache is present), not a measurement to diff.
 *
 * @type {Set<string>}
 */
const DIFF_EXCLUDED_METRICS = new Set( [ 'wpExtObjCache' ] );

/**
 * Total number of KPI targets.
 *
 * @type {number}
 */
const TARGETS_TOTAL = KPI_DEFINITIONS.length;

/**
 * Significance level (alpha) for the two-sample significance test. A metric's
 * before/after difference is flagged significant when its two-tailed p-value is
 * strictly below this value. 0.05 is the conventional 95%-confidence threshold.
 *
 * @type {number}
 */
const SIGNIFICANCE_ALPHA = 0.05;

/**
 * Deterministic fallback report timestamp (the Unix epoch) used only when
 * neither `BENCHMARK_GENERATED_AT` nor `SOURCE_DATE_EPOCH` is set. Using a fixed
 * sentinel instead of the wall-clock time keeps the emitted artifacts
 * byte-for-byte identical for identical inputs, so committed representative
 * reports preserve `git diff --exit-code` semantics. Real runs are expected to
 * export one of the two overrides to record an accurate time.
 *
 * @type {string}
 */
const DEFAULT_GENERATED_AT = '1970-01-01T00:00:00.000Z';

/**
 * Lanczos series coefficients (g = 7, n = 9) for the log-gamma approximation.
 * These fixed rational constants make `logGamma` fully deterministic.
 *
 * @type {number[]}
 */
const LANCZOS_G = 7;
const LANCZOS_COEFFICIENTS = [
	0.99999999999980993, 676.5203681218851, -1259.1392167224028,
	771.32342877765313, -176.61502916214059, 12.507343278686905,
	-0.13857109526572012, 9.9843695780195716e-6, 1.5056327351493116e-7,
];

/**
 * Rounds a number to a fixed number of decimal places, passing non-finite
 * values (Infinity/-Infinity/NaN) through unchanged so degenerate t-statistics
 * are not corrupted. Rounding keeps the emitted statistics stable and
 * deterministic across runs.
 *
 * @param {number} value    Value to round.
 * @param {number} decimals Number of decimal places.
 * @return {number} Rounded value, or the original value when non-finite.
 */
function roundTo( value, decimals ) {
	if ( ! Number.isFinite( value ) ) {
		return value;
	}

	const factor = Math.pow( 10, decimals );
	return Math.round( value * factor ) / factor;
}

/**
 * Arithmetic mean of a numeric sample.
 *
 * @param {number[]} samples Samples.
 * @return {number} Mean (NaN for an empty array).
 */
function mean( samples ) {
	if ( samples.length === 0 ) {
		return NaN;
	}

	let total = 0;
	for ( const value of samples ) {
		total += value;
	}

	return total / samples.length;
}

/**
 * Unbiased (Bessel-corrected, `n - 1` denominator) sample variance.
 *
 * The shared `standardDeviation` helper in utils.js uses the population
 * (`n`) denominator, which is why this test computes its own variance: the
 * Welch t-test requires the unbiased estimator.
 *
 * @param {number[]} samples   Samples (length >= 2).
 * @param {number}   meanValue Precomputed mean of `samples`.
 * @return {number} Sample variance.
 */
function sampleVariance( samples, meanValue ) {
	let sumSquares = 0;
	for ( const value of samples ) {
		const diff = value - meanValue;
		sumSquares += diff * diff;
	}

	return sumSquares / ( samples.length - 1 );
}

/**
 * Natural logarithm of the gamma function via the Lanczos approximation.
 *
 * Uses the reflection formula for arguments below 0.5 so the approximation is
 * valid across the whole real line. Deterministic for a given input.
 *
 * @param {number} x Argument.
 * @return {number} ln( gamma( x ) ).
 */
function logGamma( x ) {
	if ( x < 0.5 ) {
		// Reflection: ln G(x) = ln( pi / sin( pi x ) ) - ln G( 1 - x ).
		return (
			Math.log( Math.PI / Math.sin( Math.PI * x ) ) - logGamma( 1 - x )
		);
	}

	x -= 1;
	let a = LANCZOS_COEFFICIENTS[ 0 ];
	const t = x + LANCZOS_G + 0.5;
	for ( let i = 1; i < LANCZOS_COEFFICIENTS.length; i++ ) {
		a += LANCZOS_COEFFICIENTS[ i ] / ( x + i );
	}

	return (
		0.5 * Math.log( 2 * Math.PI ) +
		( x + 0.5 ) * Math.log( t ) -
		t +
		Math.log( a )
	);
}

/**
 * Continued-fraction expansion for the incomplete beta function, evaluated
 * with the modified Lentz algorithm. Adapted from the standard Numerical
 * Recipes `betacf` routine.
 *
 * @param {number} a Alpha parameter.
 * @param {number} b Beta parameter.
 * @param {number} x Point in [0, 1].
 * @return {number} Continued-fraction value.
 */
function betaContinuedFraction( a, b, x ) {
	const FPMIN = 1e-300;
	const EPS = 1e-12;
	const MAX_ITERATIONS = 200;

	const qab = a + b;
	const qap = a + 1;
	const qam = a - 1;

	let c = 1;
	let d = 1 - ( qab * x ) / qap;
	if ( Math.abs( d ) < FPMIN ) {
		d = FPMIN;
	}
	d = 1 / d;
	let h = d;

	for ( let m = 1; m <= MAX_ITERATIONS; m++ ) {
		const m2 = 2 * m;

		let aa = ( m * ( b - m ) * x ) / ( ( qam + m2 ) * ( a + m2 ) );
		d = 1 + aa * d;
		if ( Math.abs( d ) < FPMIN ) {
			d = FPMIN;
		}
		c = 1 + aa / c;
		if ( Math.abs( c ) < FPMIN ) {
			c = FPMIN;
		}
		d = 1 / d;
		h *= d * c;

		aa = ( -( a + m ) * ( qab + m ) * x ) / ( ( a + m2 ) * ( qap + m2 ) );
		d = 1 + aa * d;
		if ( Math.abs( d ) < FPMIN ) {
			d = FPMIN;
		}
		c = 1 + aa / c;
		if ( Math.abs( c ) < FPMIN ) {
			c = FPMIN;
		}
		d = 1 / d;
		const del = d * c;
		h *= del;

		if ( Math.abs( del - 1 ) < EPS ) {
			break;
		}
	}

	return h;
}

/**
 * Regularized incomplete beta function I_x(a, b), computed from `logGamma` and
 * the continued fraction above. Returns a value in [0, 1].
 *
 * @param {number} x Point in [0, 1].
 * @param {number} a Alpha parameter.
 * @param {number} b Beta parameter.
 * @return {number} I_x( a, b ).
 */
function incompleteBeta( x, a, b ) {
	if ( x <= 0 ) {
		return 0;
	}

	if ( x >= 1 ) {
		return 1;
	}

	const logBeta =
		logGamma( a + b ) -
		logGamma( a ) -
		logGamma( b ) +
		a * Math.log( x ) +
		b * Math.log( 1 - x );
	const front = Math.exp( logBeta );

	// Use the fraction that converges fastest for the given x.
	if ( x < ( a + 1 ) / ( a + b + 2 ) ) {
		return ( front * betaContinuedFraction( a, b, x ) ) / a;
	}

	return 1 - ( front * betaContinuedFraction( b, a, 1 - x ) ) / b;
}

/**
 * Two-tailed p-value of a Student's t statistic with the given degrees of
 * freedom, using p = I_{df/(df + t^2)}( df / 2, 1 / 2 ).
 *
 * @param {number} t  t statistic.
 * @param {number} df Degrees of freedom (> 0).
 * @return {number} Two-tailed p-value in [0, 1].
 */
function studentTTwoTailedPValue( t, df ) {
	// An infinite t (zero standard error with differing means) is a certain
	// difference, so its two-tailed p-value is 0.
	if ( ! Number.isFinite( t ) ) {
		return 0;
	}

	if ( ! Number.isFinite( df ) || df <= 0 ) {
		return NaN;
	}

	const x = df / ( df + t * t );
	return incompleteBeta( x, df / 2, 0.5 );
}

/**
 * Welch's unequal-variances two-sample t-test between the baseline and
 * optimized samples.
 *
 * Returns `null` when either group has fewer than two samples (variance cannot
 * be estimated), so callers render the significance as blank. When both groups
 * are constant (zero pooled standard error) the test degenerates to a direct
 * mean comparison. The result is fully deterministic for identical inputs and
 * uses only Node built-ins.
 *
 * @param {number[]} beforeSamples Baseline samples.
 * @param {number[]} afterSamples  Optimized samples.
 * @param {number}   alpha         Significance level.
 * @return {?{test: string, tStatistic: number, degreesOfFreedom: number, pValue: number, significant: boolean, alpha: number}}
 *         Significance result, or `null` when it cannot be computed.
 */
function computeSignificance( beforeSamples, afterSamples, alpha ) {
	const n1 = beforeSamples.length;
	const n2 = afterSamples.length;

	// At least two samples per group are required to estimate variance.
	if ( n1 < 2 || n2 < 2 ) {
		return null;
	}

	const mean1 = mean( beforeSamples );
	const mean2 = mean( afterSamples );
	const variance1 = sampleVariance( beforeSamples, mean1 );
	const variance2 = sampleVariance( afterSamples, mean2 );

	const se1 = variance1 / n1;
	const se2 = variance2 / n2;
	const standardErrorSquared = se1 + se2;
	const standardError = Math.sqrt( standardErrorSquared );

	let tStatistic;
	let degreesOfFreedom;
	let pValue;

	if ( standardError === 0 ) {
		// Both groups are constant. Identical means => no difference;
		// differing means => a certain difference.
		if ( mean1 === mean2 ) {
			tStatistic = 0;
			pValue = 1;
		} else {
			tStatistic = mean1 > mean2 ? Infinity : -Infinity;
			pValue = 0;
		}
		degreesOfFreedom = n1 + n2 - 2;
	} else {
		tStatistic = ( mean1 - mean2 ) / standardError;
		// Welch-Satterthwaite effective degrees of freedom.
		degreesOfFreedom =
			( standardErrorSquared * standardErrorSquared ) /
			( ( se1 * se1 ) / ( n1 - 1 ) + ( se2 * se2 ) / ( n2 - 1 ) );
		pValue = studentTTwoTailedPValue( tStatistic, degreesOfFreedom );
	}

	// Clamp against tiny numerical overshoot before deriving the verdict.
	pValue = Math.min( 1, Math.max( 0, pValue ) );

	return {
		test: 'welch-two-sample-t',
		tStatistic: roundTo( tStatistic, 6 ),
		degreesOfFreedom: roundTo( degreesOfFreedom, 4 ),
		pValue: roundTo( pValue, 6 ),
		significant: pValue < alpha,
		alpha,
	};
}

/**
 * Resolves the report timestamp.
 *
 * Honours `BENCHMARK_GENERATED_AT` (an explicit ISO-8601 string) first, then
 * `SOURCE_DATE_EPOCH` (Unix seconds, the reproducible-builds convention), and
 * finally a fixed deterministic sentinel (`DEFAULT_GENERATED_AT`). The
 * fallback intentionally does NOT read the wall-clock time: with no override,
 * identical inputs must produce a byte-identical report so committed
 * representative artifacts keep `git diff --exit-code` semantics. Real runs are
 * expected to export one of the two overrides (for example the source commit
 * time) to record an accurate, still-deterministic timestamp.
 *
 * @return {string} ISO-8601 timestamp.
 */
function resolveGeneratedAt() {
	if ( process.env.BENCHMARK_GENERATED_AT ) {
		return process.env.BENCHMARK_GENERATED_AT;
	}

	if ( process.env.SOURCE_DATE_EPOCH ) {
		const epochSeconds = Number( process.env.SOURCE_DATE_EPOCH );
		if ( Number.isFinite( epochSeconds ) ) {
			return new Date( epochSeconds * 1000 ).toISOString();
		}
	}

	return DEFAULT_GENERATED_AT;
}

/**
 * Determines the measurement scope of a suite from its title prefix.
 *
 * The performance reporter builds titles as `<suite> > <variant>` (for
 * example `Admin > Locale: en_US` or `Homepage > Theme: ...`), so the leading
 * suite name identifies the scope.
 *
 * @param {string} title Suite title.
 * @return {string} One of `admin`, `front-end`, or `other`.
 */
function scopeForTitle( title ) {
	if ( title.startsWith( 'Admin' ) ) {
		return 'admin';
	}

	if ( title.startsWith( 'Homepage' ) || title.startsWith( 'Single Post' ) ) {
		return 'front-end';
	}

	return 'other';
}

/**
 * Collects every sample for a metric across the suites matching a scope.
 *
 * Values are accumulated per matching suite via `accumulateValues` (which
 * flattens the per-repetition arrays) and then merged, mirroring how
 * `compare-results.js` aggregates a suite before taking its median.
 *
 * @param {Array<{title: string, results: Array<Record<string, number[]>>}>} stats     Raw results.
 * @param {string}                                                           metricKey Metric to collect.
 * @param {string}                                                           scope     Scope to match.
 * @return {number[]} Concatenated samples (empty when nothing matches).
 */
function collectMetricSamples( stats, metricKey, scope ) {
	const samples = [];

	for ( const stat of stats ) {
		if ( scopeForTitle( stat.title ) !== scope ) {
			continue;
		}

		const accumulated = accumulateValues( stat.results );
		if ( Array.isArray( accumulated[ metricKey ] ) ) {
			samples.push( ...accumulated[ metricKey ] );
		}
	}

	return samples;
}

/**
 * Computes the median of a sample array, or `null` when it is empty.
 *
 * `median()` from utils is not null-safe for empty arrays, so this guard
 * preserves the missing-baseline resilience required by the report contract.
 *
 * @param {number[]} samples Samples.
 * @return {number|null} Median, or `null` when there are no samples.
 */
function medianOrNull( samples ) {
	return samples.length > 0 ? median( samples ) : null;
}

/**
 * Evaluates a single KPI target against the baseline and optimized samples.
 *
 * The KPI verdict uses the conventional reduction-over-baseline formula
 * `( before - after ) / before * 100` (positive = improvement). The per-suite
 * `deltaPct` shares the same `before` denominator but keeps the opposite sign
 * convention `( after - before ) / before * 100` (negative = improvement); the
 * two columns are reconciled by the "Percentage conventions" legend emitted at
 * the top of the report. The entry also carries a `significance` object
 * (Welch's two-sample t-test over the raw baseline/optimized samples), or
 * `null` when it cannot be computed.
 *
 * @param {Object}                                                           definition  KPI definition.
 * @param {Array<{title: string, results: Array<Record<string, number[]>>}>} afterStats  Optimized raw results.
 * @param {Array<{title: string, results: Array<Record<string, number[]>>}>} beforeStats Baseline raw results.
 * @param {boolean}                                                          hasBaseline Whether a baseline is available.
 * @return {Object} KPI report entry, including a `significance` field.
 */
function evaluateKpi( definition, afterStats, beforeStats, hasBaseline ) {
	const { id, label, metric, scope, unit, threshold } = definition;

	const afterSamples = collectMetricSamples( afterStats, metric, scope );
	const beforeSamples = hasBaseline
		? collectMetricSamples( beforeStats, metric, scope )
		: [];

	const after = medianOrNull( afterSamples );
	const before = medianOrNull( beforeSamples );

	// Reduction over baseline. Null when the baseline is missing or zero, or
	// when the optimized metric is absent, so we never divide by zero.
	const reductionPct =
		before !== null && before !== 0 && after !== null
			? ( ( before - after ) / before ) * 100
			: null;

	const passed = reductionPct !== null && reductionPct >= threshold;

	// Two-sample significance of the before/after difference. Null when there
	// are too few samples (for example when no baseline is available).
	const significance = computeSignificance(
		beforeSamples,
		afterSamples,
		SIGNIFICANCE_ALPHA
	);

	return {
		id,
		label,
		metric,
		scope,
		unit,
		before,
		after,
		beforeFormatted: formatValue( metric, before ),
		afterFormatted: formatValue( metric, after ),
		reductionPct,
		threshold,
		direction: 'reduction',
		passed,
		significance,
	};
}

/**
 * Builds the per-suite, per-metric statistics for every optimized suite.
 *
 * `before`/`after` are medians of the accumulated samples,
 * `deltaAbs = after - before`, and `deltaPct = ( after - before ) / before *
 * 100` (a signed change over the BASELINE value). The denominator is `before`
 * so this per-suite "Diff %" shares a single denominator with the KPI table's
 * "Reduction %" (also over `before`), resolving the dual-denominator ambiguity
 * (INFO-3); this intentionally diverges from `compare-results.js`, which
 * normalises over `after`. `wpExtObjCache` is excluded from delta math.
 * Baseline values are only compared when the repetition counts match. Each
 * metric also carries a `significance` object (Welch's two-sample t-test over
 * the raw baseline/optimized samples), or `null` when it cannot be computed.
 *
 * @param {Array<{title: string, results: Array<Record<string, number[]>>}>} afterStats  Optimized raw results.
 * @param {Array<{title: string, results: Array<Record<string, number[]>>}>} beforeStats Baseline raw results.
 * @return {Array<{title: string, scope: string, metrics: Array<Object>}>} Per-suite statistics.
 */
function buildSuites( afterStats, beforeStats ) {
	const suites = [];

	for ( const { title, results } of afterStats ) {
		const prevStat = beforeStats.find( ( s ) => s.title === title );

		const newResults = accumulateValues( results );
		// Only compare when the number of repetitions matches, exactly as
		// compare-results.js does.
		const prevResults =
			prevStat && prevStat.results.length === results.length
				? accumulateValues( prevStat.results )
				: {};

		const metrics = [];

		for ( const [ metric, values ] of Object.entries( newResults ) ) {
			const after = median( values );
			const prevValues = Array.isArray( prevResults[ metric ] )
				? prevResults[ metric ]
				: null;
			const before = prevValues ? median( prevValues ) : null;
			const excluded = DIFF_EXCLUDED_METRICS.has( metric );

			const deltaAbs =
				! excluded && before !== null ? after - before : null;
			// Signed change over the BASELINE: deltaPct = ( after - before ) /
			// before * 100. The denominator is deliberately `before` so that
			// this per-suite "Diff %" shares one denominator with the KPI
			// table's "Reduction %" (also over `before`); the columns differ
			// only in sign convention (see the "Percentage conventions" legend
			// emitted at the top of the report). This intentionally diverges
			// from compare-results.js (which normalises over `after`) to keep
			// the benchmark report internally consistent (resolves INFO-3).
			const deltaPct =
				! excluded && before && after !== 0
					? ( ( after - before ) / before ) * 100
					: null;

			// Two-sample significance of this metric's before/after difference.
			// Null for excluded metrics and when no comparable baseline samples
			// are available.
			const significance =
				! excluded && prevValues
					? computeSignificance(
							prevValues,
							values,
							SIGNIFICANCE_ALPHA
					  )
					: null;

			metrics.push( {
				metric,
				before,
				after,
				deltaAbs,
				deltaPct,
				std: standardDeviation( values ),
				mad: medianAbsoluteDeviation( values ),
				significance,
			} );
		}

		suites.push( {
			title,
			scope: scopeForTitle( title ),
			metrics,
		} );
	}

	return suites;
}

/**
 * Formats the "Significant" table cell for a per-metric or per-KPI significance result.
 *
 * Returns the supplied fallback string when no significance result is available
 * ( `null` ), otherwise `'yes'` / `'no'` depending on whether the Welch t-test
 * reached significance. Extracted into a helper so the calling table builders use
 * a single, flat conditional rather than a nested ternary expression.
 *
 * @param {?{significant: boolean}} significance Significance result, or null when unavailable.
 * @param {string}                  fallback     Value to use when significance is null.
 * @return {string} The formatted cell value.
 */
function formatSignificantLabel( significance, fallback ) {
	if ( null === significance ) {
		return fallback;
	}

	return significance.significant ? 'yes' : 'no';
}

/**
 * Builds the KPI summary table rows for Markdown/console rendering.
 *
 * @param {Array<Object>} kpis KPI report entries.
 * @return {Array<Record<string, string>>} Table rows.
 */
function buildKpiRows( kpis ) {
	return kpis.map( ( kpi ) => ( {
		Target: kpi.label,
		Metric: kpi.metric,
		Before: kpi.beforeFormatted,
		After: kpi.afterFormatted,
		'Reduction %':
			kpi.reductionPct !== null
				? `${ kpi.reductionPct.toFixed( 2 ) } %`
				: 'N/A',
		Threshold: `>= ${ kpi.threshold }%`,
		Status: kpi.passed ? 'PASS' : 'FAIL',
		'p-value':
			kpi.significance !== null
				? kpi.significance.pValue.toFixed( 4 )
				: 'N/A',
		Significant: formatSignificantLabel( kpi.significance, 'N/A' ),
	} ) );
}

/**
 * Builds the per-suite Markdown table rows for a single suite.
 *
 * Extends the `Metric / Before / After / Diff abs. / Diff % / STD / MAD` shape
 * from `compare-results.js` with `p-value` / `Significant` columns from the
 * per-metric Welch t-test; diff and significance columns are blank for excluded
 * metrics and when no comparable baseline value is available.
 *
 * @param {Array<Object>} metrics Per-metric statistics.
 * @return {Array<Record<string, string>>} Table rows.
 */
function buildSuiteRows( metrics ) {
	return metrics.map( ( entry ) => {
		const excluded = DIFF_EXCLUDED_METRICS.has( entry.metric );

		return {
			Metric: entry.metric,
			Before:
				entry.before !== null
					? formatValue( entry.metric, entry.before )
					: 'N/A',
			After: formatValue( entry.metric, entry.after ),
			'Diff abs.':
				entry.deltaAbs !== null
					? formatValue( entry.metric, entry.deltaAbs )
					: '',
			'Diff %':
				entry.deltaPct !== null
					? `${ entry.deltaPct.toFixed( 2 ) } %`
					: '',
			STD: excluded ? '' : formatValue( entry.metric, entry.std ),
			MAD: excluded ? '' : formatValue( entry.metric, entry.mad ),
			'p-value':
				entry.significance !== null
					? entry.significance.pValue.toFixed( 4 )
					: '',
			Significant: formatSignificantLabel( entry.significance, '' ),
		};
	} );
}

/**
 * Assembles the full human-readable Markdown diff report.
 *
 * @param {Object} report The machine-readable report object.
 * @return {string} Markdown document, terminated by a single newline.
 */
function buildMarkdown( report ) {
	const lines = [];

	lines.push( '# Benchmark Diff Report' );
	lines.push( '' );
	lines.push( `- Generated at: ${ report.generatedAt }` );
	lines.push(
		`- Baseline: \`${ report.baselineFile }\` ${
			report.hasBaseline ? '(present)' : '(MISSING)'
		}`
	);
	lines.push( `- Optimized: \`${ report.optimizedFile }\`` );
	lines.push(
		`- Repetitions: ${
			report.repetitions !== null ? report.repetitions : 'N/A'
		}`
	);
	lines.push(
		`- Iterations: ${
			report.iterations !== null ? report.iterations : 'N/A'
		}`
	);
	lines.push( '' );

	if ( ! report.hasBaseline ) {
		lines.push(
			'> No baseline results were found, so before values and reductions are reported as N/A.'
		);
		lines.push( '' );
	}

	lines.push( '## Percentage conventions' );
	lines.push( '' );
	lines.push(
		'All percentages in this report use the **baseline (`before`) value as the denominator**. Two column names appear, differing only in sign:'
	);
	lines.push( '' );
	lines.push(
		'- **`Reduction %`** (KPI Targets table) = `(before - after) / before * 100`. **Positive = improvement** (the metric got smaller). A KPI passes when `Reduction %` >= its threshold.'
	);
	lines.push(
		'- **`Diff %`** (Per-Suite Metrics tables) = `(after - before) / before * 100`. **Negative = improvement** (the metric got smaller); positive = regression.'
	);
	lines.push( '' );
	lines.push(
		'The two columns are algebraic negations of each other over the same `before` denominator (`Reduction % = -Diff %`), so a single metric delta reads consistently across every table. Percentages are `null`/blank when no baseline is present or the baseline value is zero. Lower is better for every metric reported here.'
	);
	lines.push( '' );

	lines.push( '## KPI Targets' );
	lines.push( '' );
	lines.push( formatAsMarkdownTable( buildKpiRows( report.kpis ) ) );
	lines.push(
		`Targets met: ${ report.summary.targetsMet } / ${
			report.summary.targetsTotal
		}${ report.summary.allTargetsMet ? ' (all targets met)' : '' }`
	);
	lines.push( '' );

	lines.push( '## Per-Suite Metrics' );
	lines.push( '' );

	if ( report.suites.length === 0 ) {
		lines.push( '(no suites)' );
		lines.push( '' );
	}

	for ( const suite of report.suites ) {
		lines.push( `### ${ suite.title } (scope: ${ suite.scope })` );
		lines.push( '' );
		lines.push( formatAsMarkdownTable( buildSuiteRows( suite.metrics ) ) );
	}

	return `${ lines.join( '\n' ).replace( /\n+$/, '' ) }\n`;
}

/**
 * Generates the benchmark diff report and KPI verdicts, writing the JSON and
 * Markdown artifacts and printing a concise KPI summary to stdout.
 *
 * @return {void}
 */
function main() {
	const beforeStats = parseFile( BASELINE_FILE );
	const afterStats = parseFile( OPTIMIZED_FILE );

	const hasBaseline = beforeStats.length > 0;

	// Opt-out for the documented baseline-optional ("N/A") mode. By default the
	// generator FAILS LOUDLY when a declared input artifact is missing, so the
	// committed benchmark-report.json contract can never silently regress to
	// fabricated or empty numbers (the review finding: declared inputs absent
	// yet a full report emitted). Passing --allow-missing-baseline (or setting
	// BENCHMARK_ALLOW_MISSING_BASELINE=1) re-enables the N/A degradation for the
	// legitimate "optimized-only, no baseline captured yet" workflow.
	const allowMissingBaseline =
		process.argv.slice( 2 ).includes( '--allow-missing-baseline' ) ||
		process.env.BENCHMARK_ALLOW_MISSING_BASELINE === '1';

	if ( afterStats.length === 0 ) {
		process.stderr.write(
			`Error: no optimized results found at ${ join(
				process.env.WP_ARTIFACTS_PATH,
				OPTIMIZED_FILE
			) }. Run the optimized benchmark before generating a report.\n`
		);
		process.exit( 1 );
	}

	if ( ! hasBaseline && ! allowMissingBaseline ) {
		process.stderr.write(
			`Error: no baseline results found at ${ join(
				process.env.WP_ARTIFACTS_PATH,
				BASELINE_FILE
			) }. Commit/provide the baseline artifact (run benchmarks/run-baseline.sh), or ` +
				`pass --allow-missing-baseline to emit an N/A report without a baseline.\n`
		);
		process.exit( 1 );
	}

	// Repetition and iteration counts, guarded against empty structures.
	const firstSuite = afterStats[ 0 ];
	const repetitions =
		firstSuite && Array.isArray( firstSuite.results )
			? firstSuite.results.length
			: null;

	let iterations = null;
	if ( repetitions ) {
		const firstRepetition = firstSuite.results[ 0 ];
		const firstMetricValues = firstRepetition
			? Object.values( firstRepetition )[ 0 ]
			: undefined;
		iterations = Array.isArray( firstMetricValues )
			? firstMetricValues.length
			: null;
	}

	const kpis = KPI_DEFINITIONS.map( ( definition ) =>
		evaluateKpi( definition, afterStats, beforeStats, hasBaseline )
	);

	const targetsMet = kpis.filter( ( kpi ) => kpi.passed ).length;

	const report = {
		generatedAt: resolveGeneratedAt(),
		baselineFile: BASELINE_FILE,
		optimizedFile: OPTIMIZED_FILE,
		hasBaseline,
		repetitions,
		iterations,
		kpis,
		suites: buildSuites( afterStats, beforeStats ),
		serverTimingMetrics: [ ...SERVER_TIMING_METRICS ],
		summary: {
			targetsMet,
			targetsTotal: TARGETS_TOTAL,
			allTargetsMet: targetsMet === TARGETS_TOTAL,
		},
	};

	// Ensure the artifacts directory exists before writing.
	mkdirSync( process.env.WP_ARTIFACTS_PATH, { recursive: true } );

	const reportPath = join(
		process.env.WP_ARTIFACTS_PATH,
		'benchmark-report.json'
	);
	writeFileSync( reportPath, `${ JSON.stringify( report, null, 2 ) }\n` );

	const markdown = buildMarkdown( report );
	const markdownPath = join(
		process.env.WP_ARTIFACTS_PATH,
		'benchmark-diff-report.md'
	);
	writeFileSync( markdownPath, markdown );

	// Optional extra summary file, mirroring compare-results.js's argv[0]. Skip
	// recognized option flags (e.g. --allow-missing-baseline) so they are never
	// mistaken for the summary-file path.
	const summaryFile = process.argv
		.slice( 2 )
		.find( ( arg ) => ! arg.startsWith( '--' ) );
	if ( summaryFile ) {
		writeFileSync( summaryFile, markdown );
	}

	// Concise KPI table to stdout, like compare-results.js.
	/* eslint-disable no-console -- This CLI intentionally prints a summary to stdout. */
	console.log( 'Benchmark KPI Results\n' );
	console.table( buildKpiRows( kpis ) );
	console.log(
		`\nTargets met: ${ targetsMet } / ${ TARGETS_TOTAL }${
			report.summary.allTargetsMet ? ' (all targets met)' : ''
		}`
	);
	console.log( `\nReport written to ${ reportPath }` );
	console.log( `Markdown written to ${ markdownPath }` );
	if ( summaryFile ) {
		console.log( `Summary written to ${ summaryFile }` );
	}
	/* eslint-enable no-console */
}

try {
	main();
} catch ( error ) {
	process.stderr.write(
		`Error generating benchmark diff report: ${ error.message }\n`
	);
	process.exit( 1 );
}
