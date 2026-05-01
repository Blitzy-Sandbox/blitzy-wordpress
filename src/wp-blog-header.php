<?php
/**
 * Loads the WordPress environment and template.
 *
 * Bridge between bootstrap and rendering: requires wp-load.php to set up
 * the WordPress environment, calls wp() to parse the request and populate
 * the main query ($wp_query), then includes template-loader.php to dispatch
 * to the appropriate theme template (single.php, page.php, archive.php, etc.).
 *
 * Guarded by $wp_did_header so this file can be required defensively from
 * multiple entry points without re-bootstrapping.
 *
 * @package WordPress
 */

// SECTION: Re-entry guard ($wp_did_header prevents double-bootstrap when this file is required from multiple entry points).
if ( ! isset( $wp_did_header ) ) {

	// Mark the bootstrap as in-progress before subsequent requires; prevents recursion if a plugin requires wp-blog-header.php during loading.
	$wp_did_header = true;

	// Load the WordPress library.
	// Loads wp-config.php -> wp-settings.php -> all of WordPress (constants, DB, hooks, plugins, theme functions).
	require_once __DIR__ . '/wp-load.php';

	// Set up the WordPress query.
	// wp() parses $_SERVER['REQUEST_URI'] via WP::parse_request(), runs the main query (WP_Query), and prepares globals ($post, $posts, $wp_query, $wp_the_query). See src/wp-includes/class-wp.php.
	wp();

	// Load the theme template.
	// template-loader.php walks the WordPress template hierarchy (embed, 404, search, front-page, home, privacy-policy, post-type-archive, taxonomy, attachment, single, page, singular, category, tag, author, date, archive, falling back to index) and includes the first matching theme template.
	// ABSPATH is the absolute filesystem path to the WordPress install root (defined in wp-load.php); WPINC is the relative path to the includes directory ('wp-includes', defined in default-constants.php). See src/wp-includes/template-loader.php.
	require_once ABSPATH . WPINC . '/template-loader.php';

}
