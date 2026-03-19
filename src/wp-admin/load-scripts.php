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

if ( empty( $load ) || count( $load ) > 100 ) {
	header( "$protocol 400 Bad Request" );
	exit;
}

/*
 * These files are loaded once per request and benefit from PHP OPcache when
 * available. OPcache stores the compiled bytecode, eliminating repeated parsing
 * and compilation overhead on subsequent requests to this lightweight endpoint.
 */
require ABSPATH . 'wp-admin/includes/noop.php';
require ABSPATH . WPINC . '/script-loader.php';
require ABSPATH . WPINC . '/version.php';

$expires_offset = 31536000; // 1 year.

$wp_scripts = new WP_Scripts();
wp_default_scripts( $wp_scripts );
wp_default_packages_vendor( $wp_scripts );
wp_default_packages_scripts( $wp_scripts );

$etag = $wp_scripts->get_etag( $load );

if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) === $etag ) {
	header( "$protocol 304 Not Modified" );
	exit;
}

/*
 * Accumulate file contents into an array and join via implode() rather than
 * repeated string concatenation ($out .= ...). This reduces intermediate
 * string allocations when concatenating many script files, as PHP can
 * calculate the final string size in a single pass.
 */
$parts = array();

foreach ( $load as $handle ) {
	if ( ! array_key_exists( $handle, $wp_scripts->registered ) ) {
		continue;
	}

	$path    = ABSPATH . $wp_scripts->registered[ $handle ]->src;
	$content = get_file( $path );

	if ( false !== $content ) {
		$parts[] = $content;
	}
}

$out = implode( "\n", $parts );

header( "Etag: $etag" );
header( 'Content-Type: application/javascript; charset=UTF-8' );
header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $expires_offset ) . ' GMT' );
header( "Cache-Control: public, max-age=$expires_offset" );
header( 'Content-Length: ' . strlen( $out ) );

echo $out;
exit;
