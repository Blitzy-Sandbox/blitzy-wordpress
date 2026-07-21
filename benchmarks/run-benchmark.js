#!/usr/bin/env node

/**
 * Benchmark orchestrator for the isolated performance harness (Feature F-011).
 *
 * This standalone Node.js CLI drives repeated runs of the existing Playwright
 * performance suite ( `npm run test:performance` ), aggregates the per-metric
 * samples emitted by the performance reporter, and writes a labeled,
 * machine-readable JSON artifact. It is invoked by the sibling shell scripts
 * `benchmarks/run-baseline.sh` and `benchmarks/run-optimized.sh`, which own the
 * pinned Docker lifecycle and export the environment ( `WP_ARTIFACTS_PATH`,
 * `WP_BASE_URL`, and so on ). The raw before/after files produced here are
 * later diffed by `benchmarks/generate-diff-report.js`, fulfilling the
 * evidence-first mandate: profile, quantify, fix, prove, document.
 *
 * Data contract ( identical to the CI performance suite ): the reporter writes
 * `performance-results.json` into `WP_ARTIFACTS_PATH` as an array of
 * `{ file, title, results }`, where `results` holds one object per repetition
 * mapping each metric key to an array of per-iteration numbers. This script
 * preserves that exact shape so downstream tooling reads it unchanged; when
 * `--runs` is greater than 1 the per-repetition objects of matching titles are
 * concatenated to yield more samples per metric.
 *
 * The statistics and formatting helpers are reused verbatim from
 * `tests/performance/utils.js` so the harness matches the CI suite exactly;
 * they are never duplicated here.
 *
 * This is a test-/development-environment tool only. It must never run in or
 * affect production.
 */

/**
 * External dependencies.
 */
const { writeFileSync, existsSync, mkdirSync } = require( 'node:fs' );
const { join } = require( 'node:path' );
const { spawnSync } = require( 'node:child_process' );

/*
 * Default the artifacts directory to `<cwd>/benchmarks/results` for the
 * benchmark harness. This MUST be set before requiring the shared measurement
 * helpers below, because `tests/performance/utils.js` resolves
 * `WP_ARTIFACTS_PATH` at import time. It uses `??=`, so an already-exported
 * value ( for example from the sibling shell scripts ) is preserved.
 */
process.env.WP_ARTIFACTS_PATH ??= join(
	process.cwd(),
	'benchmarks',
	'results'
);

/**
 * Internal dependencies.
 */
const {
	parseFile,
	median,
	standardDeviation,
	medianAbsoluteDeviation,
	accumulateValues,
	formatValue,
} = require( '../tests/performance/utils' );

/**
 * A single performance suite result, matching the reporter's output shape.
 *
 * @typedef {Object} SuiteResult
 * @property {string}                          file    Originating spec file path.
 * @property {string}                          title   Suite title, e.g. `Admin › Locale: en_US`.
 * @property {Array<Record<string, number[]>>} results One metric map per repetition.
 */

/**
 * File name the Playwright performance reporter always writes into
 * `WP_ARTIFACTS_PATH`.
 *
 * @type {string}
 */
const REPORTER_RESULTS_FILE = 'performance-results.json';

/**
 * Default output file name for baseline ( before ) runs, consumed by
 * `compare-results.js` and `generate-diff-report.js`.
 *
 * @type {string}
 */
const BASELINE_RESULTS_FILE = 'before-performance-results.json';

/**
 * Prints CLI usage information.
 *
 * @param {NodeJS.WritableStream} [stream] Destination stream ( defaults to stdout ).
 */
function printUsage( stream = process.stdout ) {
	stream.write(
		[
			'Usage: node benchmarks/run-benchmark.js [options]',
			'',
			'Drive repeated runs of the WordPress performance suite, aggregate the',
			'per-metric samples, and emit a machine-readable JSON results artifact.',
			'',
			'Options:',
			'  --label <name>   Human label for the run; also selects the default',
			'                   output file name (default: "benchmark"). A label of',
			'                   "baseline" writes "before-performance-results.json";',
			'                   any other label writes "performance-results.json".',
			'  --output <path>  Explicit path for the aggregated results JSON.',
			'  --runs <N>       Positive integer count of suite invocations; the',
			'                   repetitions are merged across runs (default: 1, or the',
			'                   BENCHMARK_RUNS environment variable when set).',
			'  --skip-run       Do not spawn the suite; only re-aggregate an existing',
			'                   performance-results.json (useful for re-processing).',
			'  --help, -h       Show this help and exit.',
			'',
			'Environment:',
			'  WP_ARTIFACTS_PATH  Directory for results files',
			'                     (default: <cwd>/benchmarks/results).',
			'  BENCHMARK_RUNS     Fallback for --runs when the flag is omitted.',
			'  TEST_RUNS          Per-suite Playwright iteration count; passed',
			'                     through untouched to preserve parity with CI.',
			'',
		].join( '\n' )
	);
}

/**
 * Resolves and validates the run count.
 *
 * Falls back to the `BENCHMARK_RUNS` environment variable, then to 1. Exits the
 * process with an error when the value is not a positive integer.
 *
 * @param {string|undefined} runsRaw Raw `--runs` value, when provided.
 * @return {number} A validated positive integer run count.
 */
function resolveRuns( runsRaw ) {
	const raw = runsRaw ?? process.env.BENCHMARK_RUNS ?? '1';
	const normalized = String( raw ).trim();
	const runs = Number( normalized );

	if ( ! /^\d+$/.test( normalized ) || runs < 1 ) {
		process.stderr.write(
			`Invalid --runs value: "${ raw }". Expected a positive integer.\n`
		);
		process.exit( 1 );
	}

	return runs;
}

/**
 * Parses command-line arguments.
 *
 * Accepts both `--flag value` and `--flag=value` forms. Unknown flags, and
 * missing or invalid values, are reported to stderr and terminate the process.
 *
 * @param {string[]} argv Arguments, typically `process.argv.slice( 2 )`.
 * @return {{label: string, output: string, runs: number, skipRun: boolean}} Parsed options.
 */
function parseArgs( argv ) {
	let label = 'benchmark';
	let output;
	let runsRaw;
	let skipRun = false;

	for ( let index = 0; index < argv.length; index++ ) {
		const arg = argv[ index ];

		let name = arg;
		let value;
		let hasInlineValue = false;

		if ( arg.startsWith( '--' ) && arg.includes( '=' ) ) {
			const equals = arg.indexOf( '=' );
			name = arg.slice( 0, equals );
			value = arg.slice( equals + 1 );
			hasInlineValue = true;
		}

		switch ( name ) {
			case '--help':
			case '-h':
				printUsage();
				process.exit( 0 );
				break;

			case '--skip-run':
				skipRun = true;
				break;

			case '--label':
			case '--output':
			case '--runs':
				if ( ! hasInlineValue ) {
					value = argv[ ++index ];
				}

				if ( undefined === value ) {
					process.stderr.write( `Missing value for ${ name }.\n` );
					process.exit( 1 );
				}

				if ( '--label' === name ) {
					label = value;
				} else if ( '--output' === name ) {
					output = value;
				} else {
					runsRaw = value;
				}
				break;

			default:
				process.stderr.write( `Unknown argument: ${ arg }\n` );
				printUsage( process.stderr );
				process.exit( 1 );
		}
	}

	const runs = resolveRuns( runsRaw );

	if ( ! output ) {
		const fileName =
			'baseline' === label
				? BASELINE_RESULTS_FILE
				: REPORTER_RESULTS_FILE;
		output = join( process.env.WP_ARTIFACTS_PATH, fileName );
	}

	return { label, output, runs, skipRun };
}

/**
 * Merges reporter suites into the accumulator, concatenating the per-repetition
 * results of matching titles so more `--runs` yield more samples per metric.
 *
 * @param {Map<string, SuiteResult>} merged Accumulator keyed by suite title.
 * @param {SuiteResult[]}            suites Parsed reporter output for one run.
 */
function mergeInto( merged, suites ) {
	for ( const suite of suites ) {
		const { file, title, results } = suite;

		if ( ! merged.has( title ) ) {
			merged.set( title, { file, title, results: [ ...results ] } );
		} else {
			merged.get( title ).results.push( ...results );
		}
	}
}

/**
 * Prints a per-title median summary of the aggregated metrics, reusing the
 * canonical formatting helpers so the display matches the CI perf suite.
 *
 * @param {SuiteResult[]} aggregated Aggregated suite results.
 */
function printSummary( aggregated ) {
	for ( const { title, results } of aggregated ) {
		const accumulated = accumulateValues( results );

		const rows = [];
		for ( const [ metric, values ] of Object.entries( accumulated ) ) {
			rows.push( {
				Metric: metric,
				Median: formatValue( metric, median( values ) ),
				STD: formatValue( metric, standardDeviation( values ) ),
				MAD: formatValue( metric, medianAbsoluteDeviation( values ) ),
			} );
		}

		process.stdout.write( `\n${ title }\n` );

		if ( rows.length > 0 ) {
			// eslint-disable-next-line no-console -- CLI reporter renders the metrics table for operators.
			console.table( rows );
		} else {
			process.stdout.write( '(no results)\n' );
		}
	}
}

/**
 * Invokes the existing Playwright performance suite once via npm, reusing the
 * exact CI configuration. Terminates the process on failure so a failed
 * measurement run never yields a partial artifact.
 *
 * @param {number} run   The current run number ( 1-based ).
 * @param {number} runs  The total number of runs.
 * @param {string} label The run label, used for logging.
 */
function runSuite( run, runs, label ) {
	process.stdout.write(
		`Running benchmark suite (run ${ run } of ${ runs }, label: ${ label })…\n`
	);

	const result = spawnSync( 'npm', [ 'run', 'test:performance' ], {
		stdio: 'inherit',
		env: process.env,
		cwd: join( __dirname, '..' ),
		shell: 'win32' === process.platform,
	} );

	if ( result.error ) {
		process.stderr.write(
			`Failed to launch the benchmark suite: ${ result.error.message }\n`
		);
		process.exit( 1 );
	}

	if ( 0 !== result.status ) {
		process.stderr.write(
			`Benchmark suite failed on run ${ run } of ${ runs } ` +
				`(exit code ${ result.status }).\n`
		);
		process.exit( result.status || 1 );
	}
}

/**
 * Entry point: orchestrates the run(s), aggregates the results, writes the
 * labeled artifact, and prints a human-readable summary.
 */
function main() {
	const { label, output, runs, skipRun } = parseArgs(
		process.argv.slice( 2 )
	);

	const artifactsPath = process.env.WP_ARTIFACTS_PATH;
	mkdirSync( artifactsPath, { recursive: true } );

	const reporterOutput = join( artifactsPath, REPORTER_RESULTS_FILE );

	/**
	 * @type {Map<string, SuiteResult>}
	 */
	const merged = new Map();

	if ( skipRun ) {
		if ( ! existsSync( reporterOutput ) ) {
			process.stderr.write(
				`No ${ REPORTER_RESULTS_FILE } found at ${ reporterOutput }.\n` +
					'Run without --skip-run to generate it first.\n'
			);
			process.exit( 1 );
		}

		mergeInto( merged, parseFile( REPORTER_RESULTS_FILE ) );
	} else {
		for ( let run = 1; run <= runs; run++ ) {
			runSuite( run, runs, label );

			if ( ! existsSync( reporterOutput ) ) {
				process.stderr.write(
					`Expected reporter output ${ reporterOutput } ` +
						'was not produced by the suite.\n'
				);
				process.exit( 1 );
			}

			/*
			 * Read each run's results before a subsequent run or the final
			 * write can overwrite them. This keeps aggregation correct even
			 * when --output is performance-results.json ( the optimized case ),
			 * because every read completes before the single final write.
			 */
			mergeInto( merged, parseFile( REPORTER_RESULTS_FILE ) );
		}
	}

	const aggregated = [ ...merged.values() ];

	if ( 0 === aggregated.length ) {
		process.stderr.write(
			'No suite results were found to aggregate; nothing was written.\n'
		);
		process.exit( 1 );
	}

	writeFileSync( output, JSON.stringify( aggregated, null, 2 ) );

	printSummary( aggregated );

	process.stdout.write(
		`Wrote ${ aggregated.length } suite result(s) to ${ output }\n`
	);
}

try {
	main();
} catch ( error ) {
	const message = error && error.message ? error.message : String( error );
	process.stderr.write( `Unexpected error: ${ message }\n` );
	process.exit( 1 );
}
