<?php
/**
 * WordPress source-tree build stub.
 *
 * Note: this file exists only to remind developers to build the assets.
 * For the real index.php that gets built and boots WordPress,
 * please refer to _index.php.
 *
 * If `wp-includes/build` exists and `wp-includes/js/jquery/jquery.js` has been
 * built, this stub delegates to _index.php transparently. Otherwise it loads
 * the minimum core needed to render a localized "please run npm install / npm
 * run build" notice via wp_die().
 *
 * @package WordPress
 */

/** Define ABSPATH as this file's directory */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/*
 * Load the actual index.php file if the assets were already built.
 * Note: WPINC is not defined yet, it is defined later in wp-settings.php.
 */
// QUIRK: presence of the built jQuery file AND the wp-includes/build directory together signal that 'npm run build' completed; either alone is insufficient.
if ( file_exists( ABSPATH . 'wp-includes/js/jquery/jquery.js' ) && is_dir( ABSPATH . 'wp-includes/build' ) ) {
	// Delegate to the runtime front controller; this stub never returns from this require.
	require_once ABSPATH . '_index.php';
	return;
}

// SECTION: Pre-build error path (loads minimum core for translation + wp_die only).
define( 'WPINC', 'wp-includes' );
// Just enough core to call wp_check_php_mysql_versions() and emit a localized error message.
require_once ABSPATH . WPINC . '/version.php';
require_once ABSPATH . WPINC . '/compat.php';
require_once ABSPATH . WPINC . '/load.php';

// Check for the required PHP version and for the MySQL extension or a database drop-in.
wp_check_php_mysql_versions();

// Standardize $_SERVER variables across setups.
wp_fix_server_vars();

define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
// Need functions.php for wp_die() and __() before the next call.
require_once ABSPATH . WPINC . '/functions.php';

// Pre-load core translations so the developer-facing "please build" message is localized.
wp_load_translations_early();

// Die with an error message.
// SECTION: Developer-facing build-required message.
$die = sprintf(
	'<p>%s</p>',
	__( 'You are running WordPress without JavaScript and CSS files. These need to be built.' )
);

$die .= '<p>' . sprintf(
	/* translators: %s: npm install */
	__( 'Before running any build tasks you need to make sure the dependencies are installed. You can install these by running %s.' ),
	'<code style="color: green;">npm install</code>'
) . '</p>';

$die .= '<ul>';
$die .= '<li>' . __( 'To build WordPress while developing, run:' ) . '<br /><br />';
$die .= '<code style="color: green;">npm run dev</code></li>';
$die .= '<li>' . __( 'To build files automatically when changing the source files, run:' ) . '<br /><br />';
$die .= '<code style="color: green;">npm run watch</code></li>';
$die .= '<li>' . __( 'To create a production build of WordPress, run:' ) . '<br /><br />';
$die .= '<code style="color: green;">npm run build</code></li>';
$die .= '</ul>';

$die .= '<p>' . sprintf(
	/* translators: 1: npm URL, 2: Handbook URL. */
	__( 'This requires <a href="%1$s">npm</a>. <a href="%2$s">Learn more about setting up your local development environment</a>.' ),
	'https://www.npmjs.com/get-npm',
	__( 'https://make.wordpress.org/core/handbook/tutorials/installing-wordpress-locally/' )
) . '</p>';

// Render the built-in WordPress error page with the developer message; halts script execution.
wp_die( $die, __( 'WordPress &rsaquo; Error' ) );
