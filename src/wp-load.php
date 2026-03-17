<?php
/**
 * Bootstrap file for setting the ABSPATH constant
 * and loading the wp-config.php file. The wp-config.php
 * file will then load the wp-settings.php file, which
 * will then set up the WordPress environment.
 *
 * If the wp-config.php file is not found then an error
 * will be displayed asking the visitor to set up the
 * wp-config.php file.
 *
 * Will also search for wp-config.php in WordPress' parent
 * directory to allow the WordPress directory to remain
 * untouched.
 *
 * @package WordPress
 */

/*
 * Record the bootstrap start time for performance observability.
 *
 * This timestamp is consumed by the Server-Timing instrumentation to calculate
 * the wp-bootstrap duration metric, providing visibility into the time spent
 * from initial entry through wp-settings.php completion.
 *
 * @since 7.0.0
 */
$GLOBALS['_wp_load_start'] = microtime( true );

/** Define ABSPATH as this file's directory */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/*
 * OPcache preload hints for critical bootstrap files.
 *
 * When OPcache is available and enabled, proactively compile the critical files
 * that wp-settings.php will require shortly after this point. This reduces
 * cold-start latency by ensuring these files are already in the opcode cache
 * before they are included. Files already cached are skipped to avoid redundant
 * compilation. The @ operator suppresses errors on restricted environments where
 * opcache_compile_file() may be disabled via disable_functions.
 *
 * @since 7.0.0
 */
if ( function_exists( 'opcache_compile_file' ) && ini_get( 'opcache.enable' ) ) {
	$_wp_opcache_preload_files = array(
		ABSPATH . 'wp-settings.php',
		ABSPATH . 'wp-includes/load.php',
		ABSPATH . 'wp-includes/plugin.php',
		ABSPATH . 'wp-includes/class-wp-hook.php',
		ABSPATH . 'wp-includes/formatting.php',
		ABSPATH . 'wp-includes/functions.php',
	);

	foreach ( $_wp_opcache_preload_files as $_wp_opcache_file ) {
		if ( function_exists( 'opcache_is_script_cached' ) && opcache_is_script_cached( $_wp_opcache_file ) ) {
			continue;
		}
		@opcache_compile_file( $_wp_opcache_file );
	}

	unset( $_wp_opcache_preload_files, $_wp_opcache_file );
}

/*
 * The error_reporting() function can be disabled in php.ini. On systems where that is the case,
 * it's best to add a dummy function to the wp-config.php file, but as this call to the function
 * is run prior to wp-config.php loading, it is wrapped in a function_exists() check.
 */
if ( function_exists( 'error_reporting' ) ) {
	/*
	 * Initialize error reporting to a known set of levels.
	 *
	 * This will be adapted in wp_debug_mode() located in wp-includes/load.php based on WP_DEBUG.
	 * @see https://www.php.net/manual/en/errorfunc.constants.php List of known error levels.
	 */
	error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );
}

/*
 * If wp-config.php exists in the WordPress root, or if it exists in the root and wp-settings.php
 * doesn't, load wp-config.php. The secondary check for wp-settings.php has the added benefit
 * of avoiding cases where the current directory is a nested installation, e.g. / is WordPress(a)
 * and /blog/ is WordPress(b).
 *
 * If neither set of conditions is true, initiate loading the setup process.
 *
 * The parent directory path is cached in a local variable to avoid three redundant
 * dirname() calls in the fallback branch. On the common path (config in ABSPATH),
 * the variable is computed but the cost is a single function call versus the savings
 * of eliminating repeated calls when the parent-directory fallback is evaluated.
 */
$_wp_abspath_parent = dirname( ABSPATH );

if ( file_exists( ABSPATH . 'wp-config.php' ) ) {

	/** The config file resides in ABSPATH */
	require_once ABSPATH . 'wp-config.php';

} elseif ( @file_exists( $_wp_abspath_parent . '/wp-config.php' ) && ! @file_exists( $_wp_abspath_parent . '/wp-settings.php' ) ) {

	/** The config file resides one level above ABSPATH but is not part of another installation */
	require_once $_wp_abspath_parent . '/wp-config.php';

} else {

	// A config file doesn't exist.

	define( 'WPINC', 'wp-includes' );
	require_once ABSPATH . WPINC . '/version.php';
	require_once ABSPATH . WPINC . '/compat.php';
	require_once ABSPATH . WPINC . '/load.php';

	// Check for the required PHP version and for the MySQL extension or a database drop-in.
	wp_check_php_mysql_versions();

	// Standardize $_SERVER variables across setups.
	wp_fix_server_vars();

	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	require_once ABSPATH . WPINC . '/functions.php';

	$path = wp_guess_url() . '/wp-admin/setup-config.php';

	// Redirect to setup-config.php.
	if ( ! str_contains( $_SERVER['REQUEST_URI'], 'setup-config' ) ) {
		header( 'Location: ' . $path );
		exit;
	}

	wp_load_translations_early();

	// Die with an error message.
	$die = '<p>' . sprintf(
		/* translators: %s: wp-config.php */
		__( "There doesn't seem to be a %s file. It is needed before the installation can continue." ),
		'<code>wp-config.php</code>'
	) . '</p>';
	$die .= '<p>' . sprintf(
		/* translators: 1: Documentation URL, 2: wp-config.php */
		__( 'Need more help? <a href="%1$s">Read the support article on %2$s</a>.' ),
		__( 'https://developer.wordpress.org/advanced-administration/wordpress/wp-config/' ),
		'<code>wp-config.php</code>'
	) . '</p>';
	$die .= '<p>' . sprintf(
		/* translators: %s: wp-config.php */
		__( "You can create a %s file through a web interface, but this doesn't work for all server setups. The safest way is to manually create the file." ),
		'<code>wp-config.php</code>'
	) . '</p>';
	$die .= '<p><a href="' . $path . '" class="button button-large">' . __( 'Create a Configuration File' ) . '</a></p>';

	wp_die( $die, __( 'WordPress &rsaquo; Error' ) );
}
