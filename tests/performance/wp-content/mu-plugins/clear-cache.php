<?php

add_action(
	'plugins_loaded',
	static function () {
		/*
		 * Deterministic cache reset for performance measurement runs.
		 *
		 * `/?clear_cache` is issued as a separate request that dies before the
		 * measured navigation, so the subsequent measured request starts from a
		 * cold, known state. This keeps before/after deltas attributable to code
		 * changes rather than warm caches (evidence-first measurement mandate).
		 */
		if ( isset( $_GET['clear_cache'] ) ) {
			if ( function_exists( 'opcache_reset' ) ) {
				opcache_reset();
			}

			if ( function_exists( 'apcu_clear_cache' ) ) {
				apcu_clear_cache();
			}

			wp_cache_flush();

			delete_expired_transients( true );

			clearstatcache( true );

			status_header( 202 );

			die;
		}
	},
	1
);
