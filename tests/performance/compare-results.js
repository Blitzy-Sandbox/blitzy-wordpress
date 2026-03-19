#!/usr/bin/env node

/**
 * External dependencies.
 */
const { readFileSync, writeFileSync, existsSync } = require( 'node:fs' );
const { join } = require( 'node:path' );

/**
 * Internal dependencies
 */
const {
	median,
	formatAsMarkdownTable,
	formatValue,
	formatCacheRatio,
	linkToSha,
	standardDeviation,
	medianAbsoluteDeviation,
	accumulateValues,
} = require( './utils' );

process.env.WP_ARTIFACTS_PATH ??= join( process.cwd(), 'artifacts' );

const args = process.argv.slice( 2 );
const summaryFile = args[ 0 ];

/**
 * Parse test files into JSON objects.
 *
 * @param {string} fileName The name of the file.
 * @return {Array<{file: string, title: string, results: Record<string,number[]>[]}>} Parsed object.
 */
function parseFile( fileName ) {
	const file = join( process.env.WP_ARTIFACTS_PATH, fileName );
	if ( ! existsSync( file ) ) {
		return [];
	}

	return JSON.parse( readFileSync( file, 'utf8' ) );
}

/**
 * @type {Array<{file: string, title: string, results: Record<string,number[]>[]}>}
 */
const beforeStats = parseFile( 'before-performance-results.json' );

/**
 * @type {Array<{file: string, title: string, results: Record<string,number[]>[]}>}
 */
const afterStats = parseFile( 'performance-results.json' );

let summaryMarkdown = `## Performance Test Results\n\n`;

if ( process.env.TARGET_SHA ) {
	if ( beforeStats.length > 0 ) {
		if (process.env.GITHUB_SHA) {
			summaryMarkdown += `This compares the results from this commit (${linkToSha(
				process.env.GITHUB_SHA
			)}) with the ones from ${linkToSha(process.env.TARGET_SHA)}.\n\n`;
		} else {
			summaryMarkdown += `This compares the results from this commit with the ones from ${linkToSha(
				process.env.TARGET_SHA
			)}.\n\n`;
		}
	} else {
		summaryMarkdown += `Note: no build was found for the target commit ${linkToSha(process.env.TARGET_SHA)}. No comparison is possible.\n\n`;
	}
}

const numberOfRepetitions = afterStats[ 0 ].results.length;
const numberOfIterations = Object.values( afterStats[ 0 ].results[ 0 ] )[ 0 ]
	.length;

const repetitions = `${ numberOfRepetitions } ${
	numberOfRepetitions === 1 ? 'repetition' : 'repetitions'
}`;
const iterations = `${ numberOfIterations } ${
	numberOfIterations === 1 ? 'iteration' : 'iterations'
}`;

summaryMarkdown += `All numbers are median values over ${ repetitions } with ${ iterations } each.\n\n`;

if ( process.env.GITHUB_SHA ) {
	summaryMarkdown += `**Note:** Due to the nature of how GitHub Actions work, some variance in the results is expected.\n\n`;
}

console.log( 'Performance Test Results\n' );

console.log(
	`All numbers are median values over ${ repetitions } with ${ iterations } each.\n`
);

if ( process.env.GITHUB_SHA ) {
	console.log(
		'Note: Due to the nature of how GitHub Actions work, some variance in the results is expected.\n'
	);
}

summaryMarkdown += `<details><summary>Results</summary>`;

/**
 * Collected per-metric comparison data for the performance target summary.
 * Populated during the main comparison loop below, then consumed after the loop
 * to generate the AAP §0.8.4 target status table.
 *
 * @type {Record<string, Array<{title: string, before: number, after: number, reductionPct: number}>>}
 */
const targetMetricResults = {};

for ( const { title, results } of afterStats ) {
	const prevStat = beforeStats.find( ( s ) => s.title === title );

	/**
	 * @type {Array<Record<string, string>>}
	 */
	const rows = [];

	const newResults = accumulateValues( results );
	// Only do comparison if the number of results is the same.
	const prevResults =
		prevStat && prevStat.results.length === results.length
			? accumulateValues( prevStat.results )
			: {};

	for ( const [ metric, values ] of Object.entries( newResults ) ) {
		const prevValues = prevResults[ metric ] ? prevResults[ metric ] : null;

		const value = median( values );
		const prevValue = prevValues ? median( prevValues ) : 0;
		const delta = value - prevValue;
		const percentage = ( delta / value ) * 100;
		// Non-numeric/boolean metrics excluded from percentage diff display.
		// All new count metrics (wpFilesLoaded, wpCacheHits, wpCacheMisses, wpDbQueries)
		// and timing metrics (wpBootstrap, wpPlugins) show diffs normally.
		const nonDiffMetrics = [ 'wpExtObjCache' ];
		const showDiff =
			! nonDiffMetrics.includes( metric ) && ! Number.isNaN( percentage );

		rows.push( {
			Metric: metric,
			Before: prevValues ? formatValue( metric, prevValue ) : 'N/A',
			After: formatValue( metric, value ),
			'Diff abs.': showDiff ? formatValue( metric, delta ) : '',
			'Diff %': showDiff ? `${ percentage.toFixed( 2 ) } %` : '',
			STD: showDiff
				? formatValue( metric, standardDeviation( values ) )
				: '',
			MAD: showDiff
				? formatValue( metric, medianAbsoluteDeviation( values ) )
				: '',
		} );

		// Collect comparison data for the performance target summary section.
		// Only track metrics where valid before data exists for meaningful comparison.
		if ( prevValues && prevValue !== 0 ) {
			if ( ! targetMetricResults[ metric ] ) {
				targetMetricResults[ metric ] = [];
			}
			targetMetricResults[ metric ].push( {
				title,
				before: prevValue,
				after: value,
				reductionPct: ( ( prevValue - value ) / prevValue ) * 100,
			} );
		}
	}

	console.log( title );
	if ( rows.length > 0 ) {
		console.table( rows );
	} else {
		console.log( '(no results)' );
	}

	summaryMarkdown += `<b>${ title }</b>\n\n`;
	summaryMarkdown += `${ formatAsMarkdownTable( rows ) }\n`;
}

summaryMarkdown += `</details>`;

/*
 * Performance Target Status — per AAP §0.8.4.
 *
 * Generates an informational summary comparing measured results against
 * the documented optimization targets. This section is additive — it does
 * not cause test failures, only highlights whether targets are met.
 *
 * Targets:
 *   - Front-end TTFB: ≥20% reduction
 *   - Admin DOMContentLoaded: ≥15% reduction
 *   - PHP memory per front-end request: ≥10% reduction
 *   - DB queries per front-end page load: ≥15% reduction
 *   - PHP files loaded per front-end request: ≥30% reduction
 */
if ( beforeStats.length > 0 ) {
	const performanceTargets = [
		{ metric: 'timeToFirstByte', label: 'Front-end TTFB', targetPct: 20 },
		{ metric: 'domContentLoaded', label: 'Admin DOMContentLoaded', targetPct: 15 },
		{ metric: 'wpMemoryUsage', label: 'PHP Memory Usage', targetPct: 10 },
		{ metric: 'wpDbQueries', label: 'DB Queries per Page', targetPct: 15 },
		{ metric: 'wpFilesLoaded', label: 'PHP Files Loaded', targetPct: 30 },
	];

	const targetRows = [];

	for ( const target of performanceTargets ) {
		const results = targetMetricResults[ target.metric ];

		if ( results && results.length > 0 ) {
			// Use the best (highest reduction) result across all test suites.
			const best = results.reduce( ( a, b ) =>
				a.reductionPct > b.reductionPct ? a : b
			);
			const met = best.reductionPct >= target.targetPct;

			targetRows.push( {
				Target: target.label,
				Goal: `≥${ target.targetPct }% reduction`,
				Before: formatValue( target.metric, best.before ),
				After: formatValue( target.metric, best.after ),
				Reduction: `${ best.reductionPct.toFixed( 1 ) }%`,
				'Test Suite': best.title,
				Status: met ? '✅ Met' : '❌ Not met',
			} );
		} else {
			targetRows.push( {
				Target: target.label,
				Goal: `≥${ target.targetPct }% reduction`,
				Before: 'N/A',
				After: 'N/A',
				Reduction: 'N/A',
				'Test Suite': 'N/A',
				Status: '⚠️ No data',
			} );
		}
	}

	// Informational cache efficiency row — not a reduction target, but useful
	// for tracking the effectiveness of object cache optimizations.
	const cacheHitsData = targetMetricResults.wpCacheHits;
	const cacheMissesData = targetMetricResults.wpCacheMisses;

	if ( cacheHitsData && cacheMissesData &&
		cacheHitsData.length > 0 && cacheMissesData.length > 0 ) {
		targetRows.push( {
			Target: 'Cache Hit Ratio',
			Goal: 'Informational',
			Before: formatCacheRatio( cacheHitsData[ 0 ].before, cacheMissesData[ 0 ].before ),
			After: formatCacheRatio( cacheHitsData[ 0 ].after, cacheMissesData[ 0 ].after ),
			Reduction: 'N/A',
			'Test Suite': cacheHitsData[ 0 ].title,
			Status: 'ℹ️',
		} );
	}

	summaryMarkdown += `\n\n## Performance Target Status\n\n`;
	summaryMarkdown += formatAsMarkdownTable( targetRows );
	summaryMarkdown += `\n`;

	// Console output for CI visibility.
	console.log( '\nPerformance Target Status\n' );
	if ( targetRows.length > 0 ) {
		console.table( targetRows );
	}
}

writeFileSync(
	join( process.env.WP_ARTIFACTS_PATH, '/performance-results.md' ),
	summaryMarkdown
);

if ( summaryFile ) {
	writeFileSync( summaryFile, summaryMarkdown );
}
