<?php
/**
 * Used to set up all core blocks used with the block editor.
 *
 * @package WordPress
 */

// Don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

define( 'BLOCKS_PATH', ABSPATH . WPINC . '/blocks/' );

// Include files required for core blocks registration.
if ( file_exists( BLOCKS_PATH . 'legacy-widget.php' ) ) {
	require BLOCKS_PATH . 'legacy-widget.php';
}
if ( file_exists( BLOCKS_PATH . 'widget-group.php' ) ) {
	require BLOCKS_PATH . 'widget-group.php';
}
/*
 * Dynamic block loading strategy:
 *
 * Admin, AJAX, CLI (WP-CLI, PHPUnit, cron), and PHP CLI SAPI contexts load
 * all 81 dynamic block files eagerly via require-dynamic-blocks.php — these
 * contexts need all block definitions immediately for editor features, AJAX
 * handlers, WP-CLI operations, and test suites.
 *
 * Front-end requests use lazy loading: only the block files for blocks that
 * actually appear in the rendered content are loaded. A pre_render_block filter
 * intercepts each block just before rendering, loads the corresponding PHP file,
 * and calls its registration function. This eliminates loading ~79 block files
 * that are never used on a typical front-end page.
 *
 * Two blocks (site-logo.php, comments.php) are always eagerly loaded because
 * they register global filters that classic themes depend on outside of block
 * rendering context (e.g., get_custom_logo(), comment_form()).
 *
 * @since 7.0.0
 */
if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'WP_CLI' ) && WP_CLI ) || 'cli' === PHP_SAPI ) {
	if ( file_exists( BLOCKS_PATH . 'require-dynamic-blocks.php' ) ) {
		require BLOCKS_PATH . 'require-dynamic-blocks.php';
	}
} else {
	// Front-end: eagerly load blocks with global side-effect hooks.
	if ( file_exists( BLOCKS_PATH . 'site-logo.php' ) ) {
		require_once BLOCKS_PATH . 'site-logo.php';
	}
	if ( file_exists( BLOCKS_PATH . 'comments.php' ) ) {
		require_once BLOCKS_PATH . 'comments.php';
	}

	// Register the lazy-loading map for all other dynamic blocks.
	// Each entry maps a fully qualified block name to its PHP file path.
	$GLOBALS['_wp_lazy_dynamic_blocks'] = array(
		'core/accordion'                    => BLOCKS_PATH . 'accordion.php',
		'core/accordion-item'               => BLOCKS_PATH . 'accordion-item.php',
		'core/archives'                     => BLOCKS_PATH . 'archives.php',
		'core/avatar'                       => BLOCKS_PATH . 'avatar.php',
		'core/block'                        => BLOCKS_PATH . 'block.php',
		'core/breadcrumbs'                  => BLOCKS_PATH . 'breadcrumbs.php',
		'core/button'                       => BLOCKS_PATH . 'button.php',
		'core/calendar'                     => BLOCKS_PATH . 'calendar.php',
		'core/categories'                   => BLOCKS_PATH . 'categories.php',
		'core/comment-author-name'          => BLOCKS_PATH . 'comment-author-name.php',
		'core/comment-content'              => BLOCKS_PATH . 'comment-content.php',
		'core/comment-date'                 => BLOCKS_PATH . 'comment-date.php',
		'core/comment-edit-link'            => BLOCKS_PATH . 'comment-edit-link.php',
		'core/comment-reply-link'           => BLOCKS_PATH . 'comment-reply-link.php',
		'core/comment-template'             => BLOCKS_PATH . 'comment-template.php',
		'core/comments-pagination'          => BLOCKS_PATH . 'comments-pagination.php',
		'core/comments-pagination-next'     => BLOCKS_PATH . 'comments-pagination-next.php',
		'core/comments-pagination-numbers'  => BLOCKS_PATH . 'comments-pagination-numbers.php',
		'core/comments-pagination-previous' => BLOCKS_PATH . 'comments-pagination-previous.php',
		'core/comments-title'               => BLOCKS_PATH . 'comments-title.php',
		'core/cover'                        => BLOCKS_PATH . 'cover.php',
		'core/details'                      => BLOCKS_PATH . 'details.php',
		'core/file'                         => BLOCKS_PATH . 'file.php',
		'core/footnotes'                    => BLOCKS_PATH . 'footnotes.php',
		'core/gallery'                      => BLOCKS_PATH . 'gallery.php',
		'core/heading'                      => BLOCKS_PATH . 'heading.php',
		'core/home-link'                    => BLOCKS_PATH . 'home-link.php',
		'core/icon'                         => BLOCKS_PATH . 'icon.php',
		'core/image'                        => BLOCKS_PATH . 'image.php',
		'core/latest-comments'              => BLOCKS_PATH . 'latest-comments.php',
		'core/latest-posts'                 => BLOCKS_PATH . 'latest-posts.php',
		'core/list'                         => BLOCKS_PATH . 'list.php',
		'core/loginout'                     => BLOCKS_PATH . 'loginout.php',
		'core/media-text'                   => BLOCKS_PATH . 'media-text.php',
		'core/navigation'                   => BLOCKS_PATH . 'navigation.php',
		'core/navigation-link'              => BLOCKS_PATH . 'navigation-link.php',
		'core/navigation-overlay-close'     => BLOCKS_PATH . 'navigation-overlay-close.php',
		'core/navigation-submenu'           => BLOCKS_PATH . 'navigation-submenu.php',
		'core/page-list'                    => BLOCKS_PATH . 'page-list.php',
		'core/page-list-item'               => BLOCKS_PATH . 'page-list-item.php',
		'core/paragraph'                    => BLOCKS_PATH . 'paragraph.php',
		'core/pattern'                      => BLOCKS_PATH . 'pattern.php',
		'core/post-author'                  => BLOCKS_PATH . 'post-author.php',
		'core/post-author-biography'        => BLOCKS_PATH . 'post-author-biography.php',
		'core/post-author-name'             => BLOCKS_PATH . 'post-author-name.php',
		'core/post-comments-count'          => BLOCKS_PATH . 'post-comments-count.php',
		'core/post-comments-form'           => BLOCKS_PATH . 'post-comments-form.php',
		'core/post-comments-link'           => BLOCKS_PATH . 'post-comments-link.php',
		'core/post-content'                 => BLOCKS_PATH . 'post-content.php',
		'core/post-date'                    => BLOCKS_PATH . 'post-date.php',
		'core/post-excerpt'                 => BLOCKS_PATH . 'post-excerpt.php',
		'core/post-featured-image'          => BLOCKS_PATH . 'post-featured-image.php',
		'core/post-navigation-link'         => BLOCKS_PATH . 'post-navigation-link.php',
		'core/post-template'                => BLOCKS_PATH . 'post-template.php',
		'core/post-terms'                   => BLOCKS_PATH . 'post-terms.php',
		'core/post-time-to-read'            => BLOCKS_PATH . 'post-time-to-read.php',
		'core/post-title'                   => BLOCKS_PATH . 'post-title.php',
		'core/query'                        => BLOCKS_PATH . 'query.php',
		'core/query-no-results'             => BLOCKS_PATH . 'query-no-results.php',
		'core/query-pagination'             => BLOCKS_PATH . 'query-pagination.php',
		'core/query-pagination-next'        => BLOCKS_PATH . 'query-pagination-next.php',
		'core/query-pagination-numbers'     => BLOCKS_PATH . 'query-pagination-numbers.php',
		'core/query-pagination-previous'    => BLOCKS_PATH . 'query-pagination-previous.php',
		'core/query-title'                  => BLOCKS_PATH . 'query-title.php',
		'core/query-total'                  => BLOCKS_PATH . 'query-total.php',
		'core/read-more'                    => BLOCKS_PATH . 'read-more.php',
		'core/rss'                          => BLOCKS_PATH . 'rss.php',
		'core/search'                       => BLOCKS_PATH . 'search.php',
		'core/shortcode'                    => BLOCKS_PATH . 'shortcode.php',
		'core/site-tagline'                 => BLOCKS_PATH . 'site-tagline.php',
		'core/site-title'                   => BLOCKS_PATH . 'site-title.php',
		'core/social-link'                  => BLOCKS_PATH . 'social-link.php',
		'core/tag-cloud'                    => BLOCKS_PATH . 'tag-cloud.php',
		'core/template-part'                => BLOCKS_PATH . 'template-part.php',
		'core/term-count'                   => BLOCKS_PATH . 'term-count.php',
		'core/term-description'             => BLOCKS_PATH . 'term-description.php',
		'core/term-name'                    => BLOCKS_PATH . 'term-name.php',
		'core/term-template'                => BLOCKS_PATH . 'term-template.php',
		'core/video'                        => BLOCKS_PATH . 'video.php',
	);

	/**
	 * Lazily loads a core dynamic block PHP file on first render.
	 *
	 * Hooked to 'pre_render_block' at priority 1, this fires before render_block_data
	 * and the WP_Block constructor. When a core block name matches an entry in the
	 * lazy-loading map, the corresponding PHP file is required and the block's
	 * registration function is called (since the 'init' hook has already fired,
	 * the add_action('init', ...) in each block file is a no-op).
	 *
	 * The function always returns null so that normal block rendering proceeds.
	 *
	 * @since 7.0.0
	 * @access private
	 *
	 * @param string|null $pre_render The pre-rendered content. Default null.
	 * @param array       $parsed_block The block being rendered.
	 * @return string|null Always null — lets render_block() continue normally.
	 */
	function _wp_lazy_load_core_dynamic_block( $pre_render, $parsed_block ) {
		if ( null !== $pre_render || empty( $parsed_block['blockName'] ) ) {
			return $pre_render;
		}

		$block_name = $parsed_block['blockName'];

		if ( ! isset( $GLOBALS['_wp_lazy_dynamic_blocks'][ $block_name ] ) ) {
			return $pre_render;
		}

		$file = $GLOBALS['_wp_lazy_dynamic_blocks'][ $block_name ];
		// Remove from map so subsequent renders of the same block type skip the lookup.
		unset( $GLOBALS['_wp_lazy_dynamic_blocks'][ $block_name ] );

		if ( file_exists( $file ) ) {
			require_once $file;

			// Each dynamic block file hooks its register function to 'init', but 'init'
			// has already fired. Manually call the registration function.
			// Convention: register_block_core_{block_name_underscored}().
			$short_name = str_replace( 'core/', '', $block_name );
			$func_name  = 'register_block_core_' . str_replace( '-', '_', $short_name );
			if ( function_exists( $func_name ) ) {
				$func_name();
			}
		}

		return $pre_render;
	}
	add_filter( 'pre_render_block', '_wp_lazy_load_core_dynamic_block', 1, 2 );
}

/**
 * Registers core block style handles.
 *
 * While {@see register_block_style_handle()} is typically used for that, the way it is
 * implemented is inefficient for core block styles. Registering those style handles here
 * avoids unnecessary logic and filesystem lookups in the other function.
 *
 * @since 6.3.0
 */
function register_core_block_style_handles() {
	$wp_version = wp_get_wp_version();

	if ( ! wp_should_load_separate_core_block_assets() ) {
		return;
	}

	$blocks_url   = includes_url( 'blocks/' );
	$suffix       = wp_scripts_get_suffix();
	$wp_styles    = wp_styles();
	$style_fields = array(
		'style'       => 'style',
		'editorStyle' => 'editor',
	);

	static $core_blocks_meta;
	if ( ! $core_blocks_meta ) {
		if ( ! file_exists( BLOCKS_PATH . 'blocks-json.php' ) ) {
			return;
		}
		$core_blocks_meta = require BLOCKS_PATH . 'blocks-json.php';
	}

	$files          = false;
	$transient_name = 'wp_core_block_css_files';

	/*
	 * Ignore transient cache when the development mode is set to 'core'. Why? To avoid interfering with
	 * the core developer's workflow.
	 */
	$can_use_cached = ! wp_is_development_mode( 'core' );

	if ( $can_use_cached ) {
		$cached_files = get_transient( $transient_name );

		// Check the validity of cached values by checking against the current WordPress version.
		if (
			is_array( $cached_files )
			&& isset( $cached_files['version'] )
			&& $cached_files['version'] === $wp_version
			&& isset( $cached_files['files'] )
		) {
			$files = $cached_files['files'];
		}
	}

	if ( ! $files ) {
		$files = glob( wp_normalize_path( BLOCKS_PATH . '**/**.css' ) );

		// Normalize BLOCKS_PATH prior to substitution for Windows environments.
		$normalized_blocks_path = wp_normalize_path( BLOCKS_PATH );

		$files = array_map(
			static function ( $file ) use ( $normalized_blocks_path ) {
				return str_replace( $normalized_blocks_path, '', $file );
			},
			$files
		);

		// Save core block style paths in cache when not in development mode.
		if ( $can_use_cached ) {
			set_transient(
				$transient_name,
				array(
					'version' => $wp_version,
					'files'   => $files,
				)
			);
		}
	}

	$register_style = static function ( $name, $filename, $style_handle ) use ( $blocks_url, $suffix, $wp_styles, $files ) {
		$style_path = "{$name}/{$filename}{$suffix}.css";
		$path       = wp_normalize_path( BLOCKS_PATH . $style_path );

		if ( ! in_array( $style_path, $files, true ) ) {
			$wp_styles->add(
				$style_handle,
				false
			);
			return;
		}

		$wp_styles->add( $style_handle, $blocks_url . $style_path );
		$wp_styles->add_data( $style_handle, 'path', $path );

		$rtl_file = "{$name}/{$filename}-rtl{$suffix}.css";
		if ( is_rtl() && in_array( $rtl_file, $files, true ) ) {
			$wp_styles->add_data( $style_handle, 'rtl', 'replace' );
			$wp_styles->add_data( $style_handle, 'suffix', $suffix );
			$wp_styles->add_data( $style_handle, 'path', str_replace( "{$suffix}.css", "-rtl{$suffix}.css", $path ) );
		}
	};

	foreach ( $core_blocks_meta as $name => $schema ) {
		/** This filter is documented in wp-includes/blocks.php */
		$schema = apply_filters( 'block_type_metadata', $schema );

		// Backfill these properties similar to `register_block_type_from_metadata()`.
		if ( ! isset( $schema['style'] ) ) {
			$schema['style'] = "wp-block-{$name}";
		}
		if ( ! isset( $schema['editorStyle'] ) ) {
			$schema['editorStyle'] = "wp-block-{$name}-editor";
		}

		// Register block theme styles.
		$register_style( $name, 'theme', "wp-block-{$name}-theme" );

		foreach ( $style_fields as $style_field => $filename ) {
			$style_handle = $schema[ $style_field ];
			if ( is_array( $style_handle ) ) {
				continue;
			}
			$register_style( $name, $filename, $style_handle );
		}
	}
}
add_action( 'init', 'register_core_block_style_handles', 9 );

/**
 * Registers core block types using metadata files.
 * Dynamic core blocks are registered separately.
 *
 * @since 5.5.0
 */
function register_core_block_types_from_metadata() {
	if ( ! file_exists( BLOCKS_PATH . 'require-static-blocks.php' ) ) {
		return;
	}
	$block_folders = require BLOCKS_PATH . 'require-static-blocks.php';
	foreach ( $block_folders as $block_folder ) {
		register_block_type_from_metadata(
			BLOCKS_PATH . $block_folder
		);
	}
}
add_action( 'init', 'register_core_block_types_from_metadata' );

/**
 * Registers the core block metadata collection.
 *
 * This function is hooked into the 'init' action with a priority of 9,
 * ensuring that the core block metadata is registered before the regular
 * block initialization that happens at priority 10.
 *
 * @since 6.7.0
 */
function wp_register_core_block_metadata_collection() {
	if ( ! file_exists( BLOCKS_PATH . 'blocks-json.php' ) ) {
		return;
	}
	wp_register_block_metadata_collection(
		BLOCKS_PATH,
		BLOCKS_PATH . 'blocks-json.php'
	);
}
add_action( 'init', 'wp_register_core_block_metadata_collection', 9 );
