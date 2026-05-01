<?php
/**
 * Front to the WordPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells WordPress to load the theme.
 *
 * This is the runtime/built front controller. In a built distribution this
 * file is renamed to index.php; in this source-tree checkout, the source
 * index.php is a stub that delegates here once npm assets exist. See
 * index.php for the build-stub logic.
 *
 * @package WordPress
 */

/**
 * Tells WordPress to load the WordPress theme and output it.
 *
 * @var bool
 */
define( 'WP_USE_THEMES', true ); // Tells WordPress to render via the active theme rather than treating this as a headless request.

/** Loads the WordPress Environment and Template */
require __DIR__ . '/wp-blog-header.php';
