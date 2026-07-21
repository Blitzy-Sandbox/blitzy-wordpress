<?php

/*
 * Test-only Server-Timing instrumentation (must-use plugin).
 *
 * Emits the seven canonical performance metrics as `Server-Timing` response
 * headers on both the front-end (`template_include`) and admin (`admin_init`)
 * paths. The metric slugs below are a STABLE CONTRACT: the sibling
 * `tests/performance/utils.js` (`camelCaseDashes` + `formatValue`) and the
 * downstream `benchmarks/` dashboard, deck, and report depend on them exactly.
 *
 * Canonical metric slugs (kebab-case header -> JS camelCase after camelCaseDashes):
 *
 *   bootstrap     -> wpBootstrap     (float duration, ms)
 *   plugins       -> wpPlugins       (float duration, ms)
 *   files-loaded  -> wpFilesLoaded   (int count, get_included_files())
 *   cache-hits    -> wpCacheHits     (int count)
 *   cache-misses  -> wpCacheMisses   (int count)
 *   db-queries    -> wpDbQueries     (int count)
 *   memory-usage  -> wpMemoryUsage   (int bytes, memory_get_peak_usage())
 *
 * Legacy slugs preserved for pass-identity: before-template, template, total,
 * ext-obj-cache (front-end emits before-template/template; admin does not).
 *
 * Duration metrics MUST be PHP floats so the emission loop auto-scales them
 * x1000 into milliseconds; counts/bytes MUST be ints so they pass through raw.
 */

/*
 * Shared early-bootstrap timestamps for the `bootstrap` and `plugins` metrics.
 *
 * Captured on early core hooks and read later by the shutdown handlers on
 * `template_include` (front-end) and `admin_init` (admin). Definitions are
 * intentionally simple and deterministic:
 *
 *   bootstrap = muplugins_loaded time - $timestart  (WP start -> mu-plugins loaded)
 *   plugins   = plugins_loaded time - muplugins_loaded time  (active plugin loading)
 */
$server_timing_bootstrap = array();

add_action(
	'muplugins_loaded',
	static function () use ( &$server_timing_bootstrap ) {
		$server_timing_bootstrap['muplugins_loaded'] = microtime( true );
	},
	PHP_INT_MIN
);

add_action(
	'plugins_loaded',
	static function () use ( &$server_timing_bootstrap ) {
		$server_timing_bootstrap['plugins_loaded'] = microtime( true );
	},
	PHP_INT_MIN
);

add_filter(
	'template_include',
	static function ( $template ) use ( &$server_timing_bootstrap ) {

		global $timestart, $wpdb;

		$server_timing_values = array();
		$template_start       = microtime( true );

		$server_timing_values['before-template'] = $template_start - $timestart;

		ob_start();

		add_action(
			'shutdown',
			static function () use ( $server_timing_values, $template_start, $wpdb, $server_timing_bootstrap, $timestart ) {
				global $wp_object_cache;

				$output = ob_get_clean();

				$server_timing_values['template'] = microtime( true ) - $template_start;

				$server_timing_values['total'] = $server_timing_values['before-template'] + $server_timing_values['template'];

				// Bootstrap + plugin-load phases (see file header for definitions).
				if ( isset( $server_timing_bootstrap['muplugins_loaded'] ) ) {
					$server_timing_values['bootstrap'] = $server_timing_bootstrap['muplugins_loaded'] - $timestart;
				}
				if ( isset( $server_timing_bootstrap['muplugins_loaded'], $server_timing_bootstrap['plugins_loaded'] ) ) {
					$server_timing_values['plugins'] = $server_timing_bootstrap['plugins_loaded'] - $server_timing_bootstrap['muplugins_loaded'];
				}

				/*
				 * While values passed via Server-Timing are intended to be durations,
				 * any numeric value can actually be passed.
				 * This is a nice little trick as it allows to easily get this information in JS.
				 */
				$server_timing_values['files-loaded']  = count( get_included_files() );
				$server_timing_values['cache-hits']    = ( isset( $wp_object_cache ) && property_exists( $wp_object_cache, 'cache_hits' ) ) ? $wp_object_cache->cache_hits : 0;
				$server_timing_values['cache-misses']  = ( isset( $wp_object_cache ) && property_exists( $wp_object_cache, 'cache_misses' ) ) ? $wp_object_cache->cache_misses : 0;
				$server_timing_values['memory-usage']  = memory_get_peak_usage();
				$server_timing_values['db-queries']    = $wpdb->num_queries;
				$server_timing_values['ext-obj-cache'] = wp_using_ext_object_cache() ? 1 : 0;

				$header_values = array();
				foreach ( $server_timing_values as $slug => $value ) {
					if ( is_float( $value ) ) {
						$value = round( $value * 1000.0, 2 );
					}
					$header_values[] = sprintf( 'wp-%1$s;dur=%2$s', $slug, $value );
				}
				header( 'Server-Timing: ' . implode( ', ', $header_values ) );

				echo $output;
			},
			PHP_INT_MIN
		);

		return $template;
	},
	PHP_INT_MAX
);

add_action(
	'admin_init',
	static function () use ( &$server_timing_bootstrap ) {
		global $timestart, $wpdb;

		ob_start();

		add_action(
			'shutdown',
			static function () use ( $wpdb, $timestart, $server_timing_bootstrap ) {
				global $wp_object_cache;

				$output = ob_get_clean();

				$server_timing_values = array();

				$server_timing_values['total'] = microtime( true ) - $timestart;

				// Bootstrap + plugin-load phases (see file header for definitions).
				if ( isset( $server_timing_bootstrap['muplugins_loaded'] ) ) {
					$server_timing_values['bootstrap'] = $server_timing_bootstrap['muplugins_loaded'] - $timestart;
				}
				if ( isset( $server_timing_bootstrap['muplugins_loaded'], $server_timing_bootstrap['plugins_loaded'] ) ) {
					$server_timing_values['plugins'] = $server_timing_bootstrap['plugins_loaded'] - $server_timing_bootstrap['muplugins_loaded'];
				}

				/*
				 * While values passed via Server-Timing are intended to be durations,
				 * any numeric value can actually be passed.
				 * This is a nice little trick as it allows to easily get this information in JS.
				 */
				$server_timing_values['files-loaded']  = count( get_included_files() );
				$server_timing_values['cache-hits']    = ( isset( $wp_object_cache ) && property_exists( $wp_object_cache, 'cache_hits' ) ) ? $wp_object_cache->cache_hits : 0;
				$server_timing_values['cache-misses']  = ( isset( $wp_object_cache ) && property_exists( $wp_object_cache, 'cache_misses' ) ) ? $wp_object_cache->cache_misses : 0;
				$server_timing_values['memory-usage']  = memory_get_peak_usage();
				$server_timing_values['db-queries']    = $wpdb->num_queries;
				$server_timing_values['ext-obj-cache'] = wp_using_ext_object_cache() ? 1 : 0;

				$header_values = array();
				foreach ( $server_timing_values as $slug => $value ) {
					if ( is_float( $value ) ) {
						$value = round( $value * 1000.0, 2 );
					}
					$header_values[] = sprintf( 'wp-%1$s;dur=%2$s', $slug, $value );
				}
				header( 'Server-Timing: ' . implode( ', ', $header_values ) );

				echo $output;
			},
			PHP_INT_MIN
		);
	},
	PHP_INT_MAX
);
