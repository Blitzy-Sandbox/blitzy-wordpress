<?php
/**
 * Loads the WordPress environment and template.
 *
 * @package WordPress
 */

if ( ! isset( $wp_did_header ) ) {

	$wp_did_header = true;

	// Record front-end bootstrap start time for Server-Timing observability.
	$GLOBALS['_wp_blog_header_start'] = microtime( true );

	// Pre-compile template-loader into OPcache before the heavy wp-load bootstrap (PHP 8.1+).
	if ( function_exists( 'opcache_is_script_cached' ) && ! opcache_is_script_cached( __DIR__ . '/wp-includes/template-loader.php' ) ) {
		opcache_compile_file( __DIR__ . '/wp-includes/template-loader.php' );
	}

	// Load the WordPress library.
	require_once __DIR__ . '/wp-load.php';

	// Set up the WordPress query.
	wp();

	// Load the theme template.
	require_once ABSPATH . WPINC . '/template-loader.php';

}
