<?php

/**
 * Timing capture variables for bootstrap and plugin loading phases.
 *
 * Initialized to 0 and updated by their respective hooks via by-reference
 * closures. The values are consumed later by the shutdown callbacks to
 * compute wp-bootstrap and wp-plugins Server-Timing metrics.
 */
$wp_perf_muplugins_loaded = 0;
$wp_perf_bootstrap_end    = 0;

/**
 * Capture the time immediately after all must-use plugins have loaded.
 *
 * Fires once during bootstrap, well before template_include or admin_init.
 */
add_action(
	'muplugins_loaded',
	static function () use ( &$wp_perf_muplugins_loaded ) {
		$wp_perf_muplugins_loaded = microtime( true );
	},
	PHP_INT_MAX
);

/**
 * Capture the time immediately after all plugins have loaded.
 *
 * Used as the endpoint of the "bootstrap" phase (timestart → plugins_loaded)
 * and the start-to-end "plugins" phase (muplugins_loaded → plugins_loaded).
 */
add_action(
	'plugins_loaded',
	static function () use ( &$wp_perf_bootstrap_end ) {
		$wp_perf_bootstrap_end = microtime( true );
	},
	PHP_INT_MAX
);

add_filter(
	'template_include',
	static function ( $template ) {

		global $timestart, $wpdb, $wp_perf_muplugins_loaded, $wp_perf_bootstrap_end;

		$server_timing_values = array();
		$template_start       = microtime( true );

		$server_timing_values['before-template'] = $template_start - $timestart;

		ob_start();

		add_action(
			'shutdown',
			static function () use ( $server_timing_values, $template_start, $wpdb, $wp_perf_bootstrap_end, $wp_perf_muplugins_loaded, $timestart ) {
				$output = ob_get_clean();

				$server_timing_values['template'] = microtime( true ) - $template_start;

				$server_timing_values['total'] = $server_timing_values['before-template'] + $server_timing_values['template'];

				/*
				 * While values passed via Server-Timing are intended to be durations,
				 * any numeric value can actually be passed.
				 * This is a nice little trick as it allows to easily get this information in JS.
				 */
				$server_timing_values['memory-usage']  = memory_get_peak_usage();
				$server_timing_values['db-queries']    = $wpdb->num_queries;
				$server_timing_values['ext-obj-cache'] = wp_using_ext_object_cache() ? 1 : 0;

				$server_timing_values['bootstrap']    = $wp_perf_bootstrap_end - $timestart;
				$server_timing_values['plugins']      = $wp_perf_bootstrap_end - $wp_perf_muplugins_loaded;
				$server_timing_values['files-loaded'] = count( get_included_files() );

				global $wp_object_cache;
				if ( isset( $wp_object_cache->cache_hits ) ) {
					$server_timing_values['cache-hits'] = $wp_object_cache->cache_hits;
				}
				if ( isset( $wp_object_cache->cache_misses ) ) {
					$server_timing_values['cache-misses'] = $wp_object_cache->cache_misses;
				}

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
	static function () {
		global $timestart, $wpdb, $wp_perf_muplugins_loaded, $wp_perf_bootstrap_end;

		ob_start();

		add_action(
			'shutdown',
			static function () use ( $wpdb, $timestart, $wp_perf_bootstrap_end, $wp_perf_muplugins_loaded ) {
				$output = ob_get_clean();

				$server_timing_values = array();

				$server_timing_values['total'] = microtime( true ) - $timestart;

				/*
				 * While values passed via Server-Timing are intended to be durations,
				 * any numeric value can actually be passed.
				 * This is a nice little trick as it allows to easily get this information in JS.
				 */
				$server_timing_values['memory-usage']  = memory_get_peak_usage();
				$server_timing_values['db-queries']    = $wpdb->num_queries;
				$server_timing_values['ext-obj-cache'] = wp_using_ext_object_cache() ? 1 : 0;

				$server_timing_values['bootstrap']    = $wp_perf_bootstrap_end - $timestart;
				$server_timing_values['plugins']      = $wp_perf_bootstrap_end - $wp_perf_muplugins_loaded;
				$server_timing_values['files-loaded'] = count( get_included_files() );

				global $wp_object_cache;
				if ( isset( $wp_object_cache->cache_hits ) ) {
					$server_timing_values['cache-hits'] = $wp_object_cache->cache_hits;
				}
				if ( isset( $wp_object_cache->cache_misses ) ) {
					$server_timing_values['cache-misses'] = $wp_object_cache->cache_misses;
				}

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
