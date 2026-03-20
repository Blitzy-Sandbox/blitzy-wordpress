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
				if ( ! headers_sent() ) {
					header( 'Server-Timing: ' . implode( ', ', $header_values ) );
				}

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

		$admin_init_time = microtime( true );

		ob_start();

		add_action(
			'shutdown',
			static function () use ( $wpdb, $timestart, $wp_perf_bootstrap_end, $wp_perf_muplugins_loaded, $admin_init_time ) {
				$output = ob_get_clean();

				$server_timing_values = array();

				$now = microtime( true );

				/*
				 * Approximate before-template and template phases for admin requests.
				 *
				 * Admin pages do not use template_include, so true template timing
				 * is unavailable. Instead, use admin_init as the template boundary:
				 * - before-template: $timestart → admin_init
				 * - template: admin_init → shutdown
				 *
				 * This provides comparable phase breakdowns for admin performance.
				 */
				$server_timing_values['before-template'] = $admin_init_time - $timestart;
				$server_timing_values['template']        = $now - $admin_init_time;
				$server_timing_values['total']           = $now - $timestart;

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
				if ( ! headers_sent() ) {
					header( 'Server-Timing: ' . implode( ', ', $header_values ) );
				}

				echo $output;
			},
			PHP_INT_MIN
		);
	},
	PHP_INT_MAX
);

/**
 * REST API Server-Timing instrumentation.
 *
 * Hooks into rest_pre_serve_request to capture and emit Server-Timing metrics
 * on REST API responses. This filter fires AFTER rest_post_dispatch (line 458
 * of class-wp-rest-server.php) but BEFORE the response body is served (line 510),
 * making it the ideal hook point: all REST processing is complete, the
 * WP_REST_Response object is fully populated, and headers have not yet been sent.
 *
 * The Server-Timing header is sent directly via PHP's header() function because
 * this filter fires after WP_REST_Server::send_headers() has already sent the
 * response object's headers, so adding to the WP_REST_Response would be too late.
 *
 * @since 7.0.0
 */
add_filter(
	'rest_pre_serve_request',
	static function ( $served, $result, $request, $server ) {
		global $timestart, $wpdb, $wp_perf_muplugins_loaded, $wp_perf_bootstrap_end;

		$now = microtime( true );

		$server_timing_values = array();

		/*
		 * Approximate before-template and template phases for REST requests.
		 *
		 * REST API requests do not use template_include, so true template timing
		 * is unavailable. Use rest_pre_serve_request as the boundary:
		 * - before-template: $timestart → now (covers bootstrap + dispatch)
		 * - template: 0 (no template rendering on REST)
		 */
		$server_timing_values['before-template'] = $now - $timestart;
		$server_timing_values['template']        = 0;
		$server_timing_values['total']           = $now - $timestart;

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

		/*
		 * Send the Server-Timing header directly via PHP's header() function.
		 *
		 * This filter fires AFTER WP_REST_Server::send_headers() has already
		 * sent the response object's headers (line 469 of class-wp-rest-server.php),
		 * so adding headers to the $result WP_REST_Response object would be too late.
		 * However, PHP has not yet started output (echo) at this point, so direct
		 * header() calls are still valid and will be included in the response.
		 *
		 * Guard against headers already sent (e.g. PHPUnit test harness output).
		 */
		if ( ! headers_sent() ) {
			header( 'Server-Timing: ' . implode( ', ', $header_values ) );
		}

		return $served;
	},
	PHP_INT_MIN,
	4
);
