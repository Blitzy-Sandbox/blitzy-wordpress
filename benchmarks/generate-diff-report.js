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
 * Inputs (read from `WP_ARTIFACTS_PATH`, default `<cwd>/benchmarks/results`):
 *   - `before-performance-results.json` : baseline raw results.
 *   - `performance-results.json`        : optimized raw results.
 *
 * Both are `Array<{ file: string, title: string, results: Array<Record<string, number[]>> }>`.
 * When the baseline file is missing, `parseFile` returns `[]`; the report is
 * still emitted with `N/A`/`null` in place of absent baseline values.
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
 * Resolves the report timestamp.
 *
 * Honours `BENCHMARK_GENERATED_AT` (an explicit ISO-8601 string) first, then
 * `SOURCE_DATE_EPOCH` (Unix seconds, the reproducible-builds convention), and
 * finally the current time. The overrides make the emitted artifacts
 * byte-for-byte reproducible for committed representative snapshots and for
 * deterministic test runs, while normal invocations record the real time.
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

	return new Date().toISOString();
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
 * The KPI verdict deliberately uses the conventional reduction-over-baseline
 * formula `( before - after ) / before * 100`, which differs from the
 * per-suite `deltaPct` (a signed change over the after value, kept for parity
 * with `compare-results.js`). Both formulas are intentional and coexist.
 *
 * @param {Object}                                                           definition  KPI definition.
 * @param {Array<{title: string, results: Array<Record<string, number[]>>}>} afterStats  Optimized raw results.
 * @param {Array<{title: string, results: Array<Record<string, number[]>>}>} beforeStats Baseline raw results.
 * @param {boolean}                                                          hasBaseline Whether a baseline is available.
 * @return {Object} KPI report entry.
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
	};
}

/**
 * Builds the per-suite, per-metric statistics for every optimized suite.
 *
 * Mirrors `compare-results.js`: `before`/`after` are medians of the
 * accumulated samples, `deltaAbs = after - before`, and
 * `deltaPct = ( after - before ) / after * 100` (a signed change over the
 * after value). `wpExtObjCache` is excluded from delta math. Baseline values
 * are only compared when the repetition counts match.
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
			// Parity with compare-results.js: percentage = ( delta / after ) * 100.
			const deltaPct =
				! excluded && before && after !== 0
					? ( ( after - before ) / after ) * 100
					: null;

			metrics.push( {
				metric,
				before,
				after,
				deltaAbs,
				deltaPct,
				std: standardDeviation( values ),
				mad: medianAbsoluteDeviation( values ),
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
	} ) );
}

/**
 * Builds the per-suite Markdown table rows for a single suite.
 *
 * Uses the `Metric / Before / After / Diff abs. / Diff % / STD / MAD` shape
 * from `compare-results.js`; diff columns are blank for excluded metrics and
 * when no baseline value is available.
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

	if ( afterStats.length === 0 ) {
		process.stderr.write(
			`Error: no optimized results found at ${ join(
				process.env.WP_ARTIFACTS_PATH,
				OPTIMIZED_FILE
			) }. Run the optimized benchmark before generating a report.\n`
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

	// Optional extra summary file, mirroring compare-results.js's argv[0].
	const summaryFile = process.argv.slice( 2 )[ 0 ];
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
