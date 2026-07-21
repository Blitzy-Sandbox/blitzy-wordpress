/**
 * WordPress dependencies
 */
import { test } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { camelCaseDashes, locales } from '../utils';

const results = {
	timeToFirstByte: [],
	domContentLoaded: [],
	adminJsTransferSize: [],
};

test.describe( 'Admin', () => {
	for ( const locale of locales ) {
		test.describe( `Locale: ${ locale }`, () => {
			test.beforeAll( async ( { requestUtils } ) => {
				await requestUtils.activateTheme( 'twentytwentyone' );
				await requestUtils.updateSiteSettings( {
					language: 'en_US' === locale ? '' : locale,
				} );
			} );

			test.afterAll( async ( { requestUtils }, testInfo ) => {
				await testInfo.attach( 'results', {
					body: JSON.stringify( results, null, 2 ),
					contentType: 'application/json',
				} );

				await requestUtils.updateSiteSettings( {
					language: '',
				} );

				results.timeToFirstByte = [];
				results.domContentLoaded = [];
				results.adminJsTransferSize = [];
			} );

			test.afterAll( async ( {}, testInfo ) => {
				await testInfo.attach( 'results', {
					body: JSON.stringify( results, null, 2 ),
					contentType: 'application/json',
				} );
			} );

			const iterations = Number( process.env.TEST_RUNS );
			for ( let i = 1; i <= iterations; i++ ) {
				test( `Measure load time metrics (${ i } of ${ iterations })`, async ( {
					page,
					admin,
					metrics,
				} ) => {
					// Clear caches using the clear-cache.php mu-plugin. Not actually loading the page.
					await page.goto( '/?clear_cache' );

					// This is the actual page to test.
					await admin.visitAdminPage( '/' );

					const serverTiming = await metrics.getServerTiming();

					for ( const [ key, value ] of Object.entries(
						serverTiming
					) ) {
						results[ camelCaseDashes( key ) ] ??= [];
						results[ camelCaseDashes( key ) ].push( value );
					}

					const ttfb = await metrics.getTimeToFirstByte();
					results.timeToFirstByte.push( ttfb );

					const domContentLoaded = await page.evaluate( () => {
						const navigation =
							performance.getEntriesByType( 'navigation' )[ 0 ];
						const activationStart = navigation.activationStart || 0;
						return (
							navigation.domContentLoadedEventEnd -
							activationStart
						);
					} );
					results.domContentLoaded.push( domContentLoaded );

					// KPI #3 instrument -- "Admin JS transfer size (gzipped)":
					// sum the over-the-wire transfer size the browser actually
					// received for every admin `.js` resource, read from the
					// Resource Timing API (PerformanceResourceTiming.transferSize).
					// Because the benchmark origin serves responses
					// gzip-compressed, transferSize IS the gzipped wire size. This
					// is a RUNTIME measurement: it captures the F-007 conditional
					// script-loading optimization (fewer scripts enqueued per admin
					// screen), NOT a static build-output byte count. F-007 shrinks
					// what each screen LOADS at runtime, not the compiled bundle
					// sizes on disk, so a static build-output analysis would not
					// reflect this optimization; reports therefore label the
					// instrument as this browser-measured gzipped transfer.
					const adminJsTransferSize = await page.evaluate( () => {
						return performance
							.getEntriesByType( 'resource' )
							.filter( ( entry ) =>
								/\.js(\?|$)/.test( entry.name )
							)
							.reduce(
								( total, entry ) => total + entry.transferSize,
								0
							);
					} );
					results.adminJsTransferSize.push( adminJsTransferSize );
				} );
			}
		} );
	}
} );
