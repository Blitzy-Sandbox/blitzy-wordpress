<?php

/*
 * The error_reporting() function can be disabled in php.ini. On systems where that is the case,
 * it's best to add a dummy function to the wp-config.php file, but as this call to the function
 * is run prior to wp-config.php loading, it is wrapped in a function_exists() check.
 */
if ( function_exists( 'error_reporting' ) ) {
	/*
	 * Disable error reporting.
	 *
	 * Set this to error_reporting( -1 ) for debugging.
	 */
	error_reporting( 0 );
}

// Set ABSPATH for execution.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

define( 'WPINC', 'wp-includes' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );

require ABSPATH . 'wp-admin/includes/noop.php';
require ABSPATH . WPINC . '/theme.php';
require ABSPATH . WPINC . '/class-wp-theme-json-resolver.php';
require ABSPATH . WPINC . '/global-styles-and-settings.php';
require ABSPATH . WPINC . '/script-loader.php';
require ABSPATH . WPINC . '/version.php';

$protocol = $_SERVER['SERVER_PROTOCOL'];
if ( ! in_array( $protocol, array( 'HTTP/1.1', 'HTTP/2', 'HTTP/2.0', 'HTTP/3' ), true ) ) {
	$protocol = 'HTTP/1.0';
}

$load = $_GET['load'];
if ( is_array( $load ) ) {
	ksort( $load );
	$load = implode( '', $load );
}

$load = preg_replace( '/[^a-z0-9,_-]+/i', '', $load );
$load = array_unique( explode( ',', $load ) );

if ( empty( $load ) ) {
	header( "$protocol 400 Bad Request" );
	exit;
}

$rtl            = ( isset( $_GET['dir'] ) && 'rtl' === $_GET['dir'] );
$expires_offset = 31536000; // 1 year.

$wp_styles = new WP_Styles();
wp_default_styles( $wp_styles );

$etag = $wp_styles->get_etag( $load );

if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) === $etag ) {
	header( "$protocol 304 Not Modified" );
	exit;
}

/*
 * Pre-compute constant replacement values used in CSS URL rewriting.
 * These strings are invariant within the request, so computing them once
 * avoids repeated string concatenation inside the loop.
 */
$wpinc_css_prefix   = '/' . WPINC . '/css/';
$wpinc_images_repl  = '../' . WPINC . '/images/';
$wpinc_tinymce_repl = '../' . WPINC . '/js/tinymce/';
$wpinc_fonts_repl   = '../' . WPINC . '/fonts/';

/*
 * Accumulate CSS content into an array and join at the end.
 * This avoids the overhead of progressively growing a string via
 * concatenation ($out .= $content), which causes repeated memory
 * reallocation as the combined output grows.
 */
$parts = array();

foreach ( $load as $handle ) {
	if ( ! array_key_exists( $handle, $wp_styles->registered ) ) {
		continue;
	}

	$style = $wp_styles->registered[ $handle ];

	if ( empty( $style->src ) ) {
		continue;
	}

	$path = ABSPATH . $style->src;

	if ( $rtl && ! empty( $style->extra['rtl'] ) ) {
		// All default styles have fully independent RTL files.
		$rtl_path = str_replace( '.min.css', '-rtl.min.css', $path );

		// Verify the RTL file exists; fall back to LTR if it is absent.
		if ( file_exists( $rtl_path ) ) {
			$path = $rtl_path;
		}
	}

	$content = get_file( $path ) . "\n";

	/*
	 * Rewrite relative URL references so they resolve correctly from
	 * the load-styles.php endpoint. WPINC styles live one directory
	 * deeper, so their ../images/ paths need an extra path segment.
	 *
	 * Note: str_starts_with() is not used here, as wp-includes/compat.php
	 * is not loaded in this file.
	 */
	if ( 0 === strpos( $style->src, $wpinc_css_prefix ) ) {
		// Single str_replace call with arrays performs one-pass rewriting
		// instead of three sequential passes over the same string.
		$content = str_replace(
			array( '../images/', '../js/tinymce/', '../fonts/' ),
			array( $wpinc_images_repl, $wpinc_tinymce_repl, $wpinc_fonts_repl ),
			$content
		);
	} else {
		$content = str_replace( '../images/', 'images/', $content );
	}

	$parts[] = $content;
}

$out = implode( '', $parts );

header( "Etag: $etag" );
header( 'Content-Type: text/css; charset=UTF-8' );
header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $expires_offset ) . ' GMT' );
header( "Cache-Control: public, max-age=$expires_offset" );
header( 'Content-Length: ' . strlen( $out ) );

echo $out;
exit;
