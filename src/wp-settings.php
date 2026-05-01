<?php
/**
 * WordPress core bootstrap orchestrator.
 *
 * Used to set up and fix common variables and include the WordPress procedural
 * and class library. Allows for some configuration in wp-config.php (see
 * default-constants.php).
 *
 * Loaded transitively by wp-config.php at the end of its own execution. This
 * file is the single largest require chain in WordPress: approximately 333
 * require/include statements that pull in roughly 1,200 source files in a
 * fixed, deterministic order. The order matters — re-arranging the requires
 * causes hard-to-debug failures because later phases assume earlier phases
 * have run.
 *
 * The phases are, in order:
 *
 *  1. **Bootstrap utilities** — version.php, compat-utf8.php, compat.php, load.php
 *  2. **Server-requirement checks** — wp_check_php_mysql_versions()
 *  3. **Initialization classes** — paused-extensions, exception/fatal-error/recovery-mode
 *  4. **Constants & plugin API** — default-constants.php, plugin.php
 *  5. **Initial constants & UTC** — wp_initial_constants(), date_default_timezone_set('UTC')
 *  6. **Server-vars normalization & maintenance probe** — wp_fix_server_vars(), wp_maintenance()
 *  7. **Debug mode & advanced-cache.php** — wp_debug_mode(), enable_loading_advanced_cache_dropin filter
 *  8. **Early classes** — list-util, token-map, utf8, formatting, meta, functions
 *  9. **Database** — require_wp_db(), wp_set_wpdb_vars(), wp_start_object_cache()
 * 10. **Default filter registrations** — default-filters.php
 * 11. **Multisite probe** — class-wp-site-query, class-wp-network-query, ms-blogs.php, ms-settings.php
 *     (only when is_multisite())
 * 12. **SHORTINIT escape hatch** — return false if SHORTINIT defined
 * 13. **L10n** — l10n.php, textdomain-registry, locale, locale-switcher
 * 14. **Install probe** — wp_not_installed()
 * 15. **Bulk core load** — capabilities/roles/user, query/date-query, theme/theme-json,
 *     templating, https detection/migration, user query/sessions, link/template/post/comment
 *     APIs, rewrite, feeds, kses, cron, deprecated, script-loader, taxonomy, update,
 *     canonical, shortcodes, embed, media, http transports, AI client, abilities,
 *     collaboration, REST + 50+ controllers, sitemaps, blocks (parser/registries/supports),
 *     style-engine, fonts, script-modules, interactivity-api, plugin-dependencies,
 *     URL pattern prefixer, speculation rules, view transitions
 * 16. **Multisite-only loads** — ms-functions.php, ms-default-filters.php, ms-deprecated.php
 * 17. **MU plugins** — wp_get_mu_plugins() + 'mu_plugin_loaded' per file
 * 18. **Network plugins** — multisite only; 'network_plugin_loaded' per file
 * 19. **'muplugins_loaded' action** — single-fire signal
 * 20. **Cookie/SSL constants** — ms_cookie_constants(), wp_cookie_constants(), wp_ssl_constants()
 * 21. **Globals & taxonomy/post-type registration** — vars.php, create_initial_taxonomies(),
 *     create_initial_post_types()
 * 22. **Theme directory registration** — register_theme_directory(get_theme_root())
 * 23. **Recovery mode initialization** — wp_recovery_mode()->initialize() (single-site only)
 * 24. **Plugin loader** — wp_get_active_and_valid_plugins() + 'plugin_loaded' per file
 * 25. **Pluggable functions** — pluggable.php, pluggable-deprecated.php
 * 26. **Internal encoding** — wp_set_internal_encoding()
 * 27. **'plugins_loaded' action** — second-fire signal
 * 28. **Functionality constants & magic_quotes** — wp_functionality_constants(), wp_magic_quotes()
 * 29. **'sanitize_comment_cookies' action** — third-fire signal
 * 30. **Core globals** — $wp_the_query, $wp_query, $wp_rewrite, $wp, $wp_widget_factory, $wp_roles
 * 31. **'setup_theme' action** — fourth-fire signal
 * 32. **Templating constants & locale** — wp_templating_constants(), wp_set_template_globals(),
 *     load_default_textdomain(), $wp_locale, $wp_locale_switcher
 * 33. **Theme functions.php loading** — for parent + child theme
 * 34. **'after_setup_theme' action** — fifth-fire signal
 * 35. **Site Health for cron** — WP_Site_Health::get_instance()
 * 36. **Current user setup** — $GLOBALS['wp']->init()
 * 37. **'init' action** — sixth-fire signal (most plugins/widgets register here)
 * 38. **Multisite site check** — ms_site_check() (multisite only; may halt with archived/deleted notice)
 * 39. **'wp_loaded' action** — final-fire signal (everything is loaded and authenticated)
 *
 * The full diagram is reproduced in `docs/architecture-diagrams.md` under the
 * heading "WordPress 7.0 Bootstrap Chain (current state)".
 *
 * ```mermaid
 * %% Title: WordPress 7.0 Bootstrap Chain (current state)
 * %% Legend: solid arrow = unconditional require; dashed arrow = conditional gated by constant or filter
 * flowchart TD
 *     wp_load[wp-load.php] --> wp_config[wp-config.php]
 *     wp_config --> wp_settings[wp-settings.php]
 *     wp_settings --> p1[1-7. Constants, version, compat, debug]
 *     p1 --> p2[8-9. Early classes + wpdb + object cache]
 *     p2 --> p3[10. default-filters.php]
 *     p3 -.->|is_multisite| p4[11. ms-blogs/ms-settings]
 *     p3 --> p5[12. SHORTINIT escape]
 *     p5 -.->|defined SHORTINIT| ret[return false]
 *     p5 --> p6[13. l10n + locale]
 *     p6 --> p7[14. wp_not_installed probe]
 *     p7 --> p8[15. Bulk core load: 200+ requires]
 *     p8 --> p9[16. Multisite-only requires]
 *     p9 --> p10[17. MU plugins + mu_plugin_loaded]
 *     p10 -.->|multisite| p11[18. Network plugins]
 *     p11 --> p12[19. muplugins_loaded action]
 *     p12 --> p13[20-21. Cookie, SSL, globals, post-types]
 *     p13 --> p14[22. Theme directory registration]
 *     p14 -.->|single-site| p15[23. Recovery mode init]
 *     p15 --> p16[24. Active plugins + plugin_loaded]
 *     p16 --> p17[25-26. Pluggable + internal encoding]
 *     p17 --> p18[27. plugins_loaded action]
 *     p18 --> p19[28-29. magic_quotes + sanitize_comment_cookies]
 *     p19 --> p20[30. Core globals: wp_query, wp, wp_roles, etc.]
 *     p20 --> p21[31. setup_theme action]
 *     p21 --> p22[32. Templating + locale]
 *     p22 --> p23[33. Theme functions.php]
 *     p23 --> p24[34. after_setup_theme action]
 *     p24 --> p25[35. Site Health init for cron]
 *     p25 --> p26[36. Current user init]
 *     p26 --> p27[37. init action]
 *     p27 -.->|multisite| p28[38. ms_site_check]
 *     p28 --> p29[39. wp_loaded action]
 * ```
 *
 * @package WordPress
 */

// SECTION: ===== Phase 1 — WPINC constant + bootstrap utilities =====.
/**
 * Stores the location of the WordPress directory of functions, classes, and core content.
 *
 * @since 1.0.0
 */
define( 'WPINC', 'wp-includes' );

// SECTION: ===== Phase 1b — Version globals + early include of version.php, compat.php, load.php =====.
/**
 * Version information for the current WordPress release.
 *
 * These can't be directly globalized in version.php. When updating,
 * include version.php from another installation and don't override
 * these values if already set.
 *
 * @global string   $wp_version              The WordPress version string.
 * @global int      $wp_db_version           WordPress database version.
 * @global string   $tinymce_version         TinyMCE version.
 * @global string   $required_php_version    The minimum required PHP version string.
 * @global string[] $required_php_extensions The names of required PHP extensions.
 * @global string   $required_mysql_version  The minimum required MySQL version string.
 * @global string   $wp_local_package        Locale code of the package.
 */
global $wp_version, $wp_db_version, $tinymce_version, $required_php_version, $required_php_extensions, $required_mysql_version, $wp_local_package;
require ABSPATH . WPINC . '/version.php';
require ABSPATH . WPINC . '/compat-utf8.php';
require ABSPATH . WPINC . '/compat.php';
require ABSPATH . WPINC . '/load.php';

// SECTION: ===== Phase 2 — Server requirement check =====.
// Check the server requirements.
wp_check_php_mysql_versions();

// SECTION: ===== Phase 3 — Initialization classes (paused extensions, exceptions, recovery mode) =====.
// Include files required for initialization.
require ABSPATH . WPINC . '/class-wp-paused-extensions-storage.php';
require ABSPATH . WPINC . '/class-wp-exception.php';
require ABSPATH . WPINC . '/class-wp-fatal-error-handler.php';
require ABSPATH . WPINC . '/class-wp-recovery-mode-cookie-service.php';
require ABSPATH . WPINC . '/class-wp-recovery-mode-key-service.php';
require ABSPATH . WPINC . '/class-wp-recovery-mode-link-service.php';
require ABSPATH . WPINC . '/class-wp-recovery-mode-email-service.php';
require ABSPATH . WPINC . '/class-wp-recovery-mode.php';
require ABSPATH . WPINC . '/error-protection.php';
require ABSPATH . WPINC . '/default-constants.php';
// SECTION: ===== Phase 4 — Plugin API + initial constants =====.
require_once ABSPATH . WPINC . '/plugin.php';

/**
 * If not already configured, `$blog_id` will default to 1 in a single site
 * configuration. In multisite, it will be overridden by default in ms-settings.php.
 *
 * @since 2.0.0
 *
 * @global int $blog_id
 */
global $blog_id;

// Set initial default constants including WP_MEMORY_LIMIT, WP_MAX_MEMORY_LIMIT, WP_DEBUG, SCRIPT_DEBUG, WP_CONTENT_DIR and WP_CACHE.
wp_initial_constants();

// SECTION: ===== Phase 5 — Shutdown handler + UTC timezone =====.
// Register the shutdown handler for fatal errors as soon as possible.
wp_register_fatal_error_handler();

// WordPress calculates offsets from UTC.
// FRAGILE: anything that calls date('Y-m-d') before this line gets server-local time, not UTC.
// phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set
date_default_timezone_set( 'UTC' );

// SECTION: ===== Phase 6 — Server vars + maintenance probe + timer =====.
// Standardize $_SERVER variables across setups.
wp_fix_server_vars();

// Check if the site is in maintenance mode.
// QUIRK: wp_maintenance() halts execution with a 503 if .maintenance file exists; this is the only check before debug mode kicks in.
wp_maintenance();

// Start loading timer.
// timer_start() captures microtime(true) for the timer_stop() helper used by the admin "Generated in X.XX seconds" footer.
timer_start();

// SECTION: ===== Phase 7 — Debug mode + advanced-cache.php drop-in =====.
// Check if WP_DEBUG mode is enabled.
wp_debug_mode();

// QUIRK: advanced-cache.php is loaded BEFORE plugins; it is the canonical hook point for full-page caches (e.g., WP Super Cache, W3 Total Cache).
/**
 * Filters whether to enable loading of the advanced-cache.php drop-in.
 *
 * This filter runs before it can be used by plugins. It is designed for non-web
 * run-times. If false is returned, advanced-cache.php will never be loaded.
 *
 * @since 4.6.0
 *
 * @param bool $enable_advanced_cache Whether to enable loading advanced-cache.php (if present).
 *                                    Default true.
 */
if ( WP_CACHE && apply_filters( 'enable_loading_advanced_cache_dropin', true ) && file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
	// For an advanced caching plugin to use. Uses a static drop-in because you would only want one.
	include WP_CONTENT_DIR . '/advanced-cache.php';

	// Re-initialize any hooks added manually by advanced-cache.php.
	// FRAGILE: advanced-cache.php may have called add_filter() on hooks that haven't been instantiated yet; rebuild the $wp_filter map to pre-initialize them.
	if ( $wp_filter ) {
		$wp_filter = WP_Hook::build_preinitialized_hooks( $wp_filter );
	}
}

// SECTION: ===== Phase 8 — Early classes (list-util, token-map, utf8, formatting, meta, functions) =====.
// Define WP_LANG_DIR if not set.
wp_set_lang_dir();

// Load early WordPress files.
require ABSPATH . WPINC . '/class-wp-list-util.php';
require ABSPATH . WPINC . '/class-wp-token-map.php';
require ABSPATH . WPINC . '/utf8.php';
require ABSPATH . WPINC . '/formatting.php';
require ABSPATH . WPINC . '/meta.php';
require ABSPATH . WPINC . '/functions.php';
require ABSPATH . WPINC . '/class-wp-meta-query.php';
require ABSPATH . WPINC . '/class-wp-matchesmapregex.php';
require ABSPATH . WPINC . '/class-wp.php';
require ABSPATH . WPINC . '/class-wp-error.php';
require ABSPATH . WPINC . '/pomo/mo.php';
require ABSPATH . WPINC . '/l10n/class-wp-translation-controller.php';
require ABSPATH . WPINC . '/l10n/class-wp-translations.php';
require ABSPATH . WPINC . '/l10n/class-wp-translation-file.php';
require ABSPATH . WPINC . '/l10n/class-wp-translation-file-mo.php';
require ABSPATH . WPINC . '/l10n/class-wp-translation-file-php.php';

// SECTION: ===== Phase 9 — Database (wpdb instance, table prefix, format specifiers) =====.
/**
 * @since 0.71
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
global $wpdb;
// Include the wpdb class and, if present, a db.php database drop-in.
// require_wp_db() loads class-wpdb.php (or a db.php drop-in if present); $wpdb is instantiated as a side effect.
require_wp_db();

/**
 * @since 3.3.0
 *
 * @global string $table_prefix The database table prefix.
 */
// QUIRK: copies wp-config's local $table_prefix to a global so multisite can override it per-site without losing the install-wide default.
if ( ! isset( $GLOBALS['table_prefix'] ) ) {
	$GLOBALS['table_prefix'] = $table_prefix;
}

// Set the database table prefix and the format specifiers for database table columns.
wp_set_wpdb_vars();

// SECTION: ===== Phase 9b — Object cache =====.
// Start the WordPress object cache, or an external object cache if the drop-in is present.
wp_start_object_cache();

// SECTION: ===== Phase 10 — Default filter registrations =====.
// Attach the default filters.
require ABSPATH . WPINC . '/default-filters.php';

// SECTION: ===== Phase 11 — Multisite probe (is_multisite() gate) =====.
// Initialize multisite if enabled.
if ( is_multisite() ) {
	require ABSPATH . WPINC . '/class-wp-site-query.php';
	require ABSPATH . WPINC . '/class-wp-network-query.php';
	require ABSPATH . WPINC . '/ms-blogs.php';
	require ABSPATH . WPINC . '/ms-settings.php';
} elseif ( ! defined( 'MULTISITE' ) ) {
	define( 'MULTISITE', false );
}

// SECTION: ===== Phase 11b — Shutdown action hook registration =====.
register_shutdown_function( 'shutdown_action_hook' );

// SECTION: ===== Phase 12 — SHORTINIT escape hatch =====.
// Stop most of WordPress from being loaded if SHORTINIT is enabled.
// SHORTINIT halts the bootstrap here; useful for lightweight scripts that only need DB+cache, e.g., XML-RPC fast paths or unit-test fixtures.
if ( SHORTINIT ) {
	return false;
}

// SECTION: ===== Phase 13 — Localization (l10n) =====.
// Load the L10n library.
require_once ABSPATH . WPINC . '/l10n.php';
require_once ABSPATH . WPINC . '/class-wp-textdomain-registry.php';
require_once ABSPATH . WPINC . '/class-wp-locale.php';
require_once ABSPATH . WPINC . '/class-wp-locale-switcher.php';

// SECTION: ===== Phase 14 — Install probe =====.
// Run the installer if WordPress is not installed.
wp_not_installed();

// SECTION: ===== Phase 15 — Bulk core load (200+ requires across capabilities, query, theme, post, comment, taxonomy, REST, blocks, fonts, interactivity) =====.
// Load most of WordPress.
require ABSPATH . WPINC . '/class-wp-walker.php';
require ABSPATH . WPINC . '/class-wp-ajax-response.php';
require ABSPATH . WPINC . '/capabilities.php';
require ABSPATH . WPINC . '/class-wp-roles.php';
require ABSPATH . WPINC . '/class-wp-role.php';
require ABSPATH . WPINC . '/class-wp-user.php';
require ABSPATH . WPINC . '/class-wp-query.php';
require ABSPATH . WPINC . '/query.php';
require ABSPATH . WPINC . '/class-wp-date-query.php';
require ABSPATH . WPINC . '/theme.php';
require ABSPATH . WPINC . '/class-wp-theme.php';
require ABSPATH . WPINC . '/class-wp-theme-json-schema.php';
require ABSPATH . WPINC . '/class-wp-theme-json-data.php';
require ABSPATH . WPINC . '/class-wp-theme-json.php';
require ABSPATH . WPINC . '/class-wp-theme-json-resolver.php';
require ABSPATH . WPINC . '/class-wp-duotone.php';
require ABSPATH . WPINC . '/global-styles-and-settings.php';
require ABSPATH . WPINC . '/class-wp-block-template.php';
require ABSPATH . WPINC . '/class-wp-block-templates-registry.php';
require ABSPATH . WPINC . '/block-template-utils.php';
require ABSPATH . WPINC . '/block-template.php';
require ABSPATH . WPINC . '/theme-templates.php';
require ABSPATH . WPINC . '/theme-previews.php';
require ABSPATH . WPINC . '/template.php';
require ABSPATH . WPINC . '/https-detection.php';
require ABSPATH . WPINC . '/https-migration.php';
require ABSPATH . WPINC . '/class-wp-user-request.php';
require ABSPATH . WPINC . '/user.php';
require ABSPATH . WPINC . '/class-wp-user-query.php';
require ABSPATH . WPINC . '/class-wp-session-tokens.php';
require ABSPATH . WPINC . '/class-wp-user-meta-session-tokens.php';
require ABSPATH . WPINC . '/general-template.php';
require ABSPATH . WPINC . '/link-template.php';
require ABSPATH . WPINC . '/author-template.php';
require ABSPATH . WPINC . '/robots-template.php';
require ABSPATH . WPINC . '/post.php';
require ABSPATH . WPINC . '/class-walker-page.php';
require ABSPATH . WPINC . '/class-walker-page-dropdown.php';
require ABSPATH . WPINC . '/class-wp-post-type.php';
require ABSPATH . WPINC . '/class-wp-post.php';
require ABSPATH . WPINC . '/post-template.php';
require ABSPATH . WPINC . '/revision.php';
require ABSPATH . WPINC . '/post-formats.php';
require ABSPATH . WPINC . '/post-thumbnail-template.php';
require ABSPATH . WPINC . '/category.php';
require ABSPATH . WPINC . '/class-walker-category.php';
require ABSPATH . WPINC . '/class-walker-category-dropdown.php';
require ABSPATH . WPINC . '/category-template.php';
require ABSPATH . WPINC . '/comment.php';
require ABSPATH . WPINC . '/class-wp-comment.php';
require ABSPATH . WPINC . '/class-wp-comment-query.php';
require ABSPATH . WPINC . '/class-walker-comment.php';
require ABSPATH . WPINC . '/comment-template.php';
require ABSPATH . WPINC . '/rewrite.php';
require ABSPATH . WPINC . '/class-wp-rewrite.php';
require ABSPATH . WPINC . '/feed.php';
require ABSPATH . WPINC . '/bookmark.php';
require ABSPATH . WPINC . '/bookmark-template.php';
require ABSPATH . WPINC . '/kses.php';
require ABSPATH . WPINC . '/cron.php';
require ABSPATH . WPINC . '/deprecated.php';
require ABSPATH . WPINC . '/script-loader.php';
// QUIRK: build/routes.php and build/pages.php are generated by `npm run build`; in a source checkout they may be absent.
if ( file_exists( ABSPATH . WPINC . '/build/routes.php' ) ) {
	require ABSPATH . WPINC . '/build/routes.php';
}
if ( file_exists( ABSPATH . WPINC . '/build/pages.php' ) ) {
	require ABSPATH . WPINC . '/build/pages.php';
}
require ABSPATH . WPINC . '/taxonomy.php';
require ABSPATH . WPINC . '/class-wp-taxonomy.php';
require ABSPATH . WPINC . '/class-wp-term.php';
require ABSPATH . WPINC . '/class-wp-term-query.php';
require ABSPATH . WPINC . '/class-wp-tax-query.php';
require ABSPATH . WPINC . '/update.php';
require ABSPATH . WPINC . '/canonical.php';
require ABSPATH . WPINC . '/shortcodes.php';
require ABSPATH . WPINC . '/embed.php';
require ABSPATH . WPINC . '/class-wp-embed.php';
require ABSPATH . WPINC . '/class-wp-oembed.php';
require ABSPATH . WPINC . '/class-wp-oembed-controller.php';
require ABSPATH . WPINC . '/media.php';
require ABSPATH . WPINC . '/http.php';
require ABSPATH . WPINC . '/html-api/html5-named-character-references.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-attribute-token.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-span.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-doctype-info.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-text-replacement.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-decoder.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-tag-processor.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-unsupported-exception.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-active-formatting-elements.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-open-elements.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-token.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-stack-event.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-processor-state.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-processor.php';
require ABSPATH . WPINC . '/class-wp-block-processor.php';
require ABSPATH . WPINC . '/class-wp-http.php';
require ABSPATH . WPINC . '/class-wp-http-streams.php';
require ABSPATH . WPINC . '/class-wp-http-curl.php';
require ABSPATH . WPINC . '/class-wp-http-proxy.php';
require ABSPATH . WPINC . '/class-wp-http-cookie.php';
require ABSPATH . WPINC . '/class-wp-http-encoding.php';
require ABSPATH . WPINC . '/class-wp-http-response.php';
require ABSPATH . WPINC . '/class-wp-http-requests-response.php';
require ABSPATH . WPINC . '/class-wp-http-requests-hooks.php';
// php-ai-client is a vendored, PHP-Scoper'd library; its autoload.php registers PSR-4 prefixes for the namespaced classes loaded next.
require ABSPATH . WPINC . '/php-ai-client/autoload.php';
require ABSPATH . WPINC . '/ai-client/adapters/class-wp-ai-client-http-client.php';
require ABSPATH . WPINC . '/ai-client/adapters/class-wp-ai-client-cache.php';
require ABSPATH . WPINC . '/ai-client/adapters/class-wp-ai-client-discovery-strategy.php';
require ABSPATH . WPINC . '/ai-client/adapters/class-wp-ai-client-event-dispatcher.php';
require ABSPATH . WPINC . '/ai-client/class-wp-ai-client-ability-function-resolver.php';
require ABSPATH . WPINC . '/ai-client/class-wp-ai-client-prompt-builder.php';
require ABSPATH . WPINC . '/ai-client.php';
require ABSPATH . WPINC . '/class-wp-connector-registry.php';
require ABSPATH . WPINC . '/connectors.php';
require ABSPATH . WPINC . '/class-wp-icons-registry.php';
require ABSPATH . WPINC . '/widgets.php';
require ABSPATH . WPINC . '/class-wp-widget.php';
require ABSPATH . WPINC . '/class-wp-widget-factory.php';
require ABSPATH . WPINC . '/nav-menu-template.php';
require ABSPATH . WPINC . '/nav-menu.php';
require ABSPATH . WPINC . '/admin-bar.php';
require ABSPATH . WPINC . '/class-wp-application-passwords.php';
require ABSPATH . WPINC . '/abilities-api/class-wp-ability-category.php';
require ABSPATH . WPINC . '/abilities-api/class-wp-ability-categories-registry.php';
require ABSPATH . WPINC . '/abilities-api/class-wp-ability.php';
require ABSPATH . WPINC . '/abilities-api/class-wp-abilities-registry.php';
require ABSPATH . WPINC . '/abilities-api.php';
require ABSPATH . WPINC . '/abilities.php';
require ABSPATH . WPINC . '/collaboration/interface-wp-sync-storage.php';
require ABSPATH . WPINC . '/collaboration/class-wp-sync-post-meta-storage.php';
require ABSPATH . WPINC . '/collaboration/class-wp-http-polling-sync-server.php';
require ABSPATH . WPINC . '/collaboration.php';
require ABSPATH . WPINC . '/rest-api.php';
require ABSPATH . WPINC . '/rest-api/class-wp-rest-server.php';
require ABSPATH . WPINC . '/rest-api/class-wp-rest-response.php';
require ABSPATH . WPINC . '/rest-api/class-wp-rest-request.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-posts-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-attachments-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-global-styles-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-post-types-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-post-statuses-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-revisions-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-global-styles-revisions-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-template-revisions-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-autosaves-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-template-autosaves-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-taxonomies-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-terms-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-menu-items-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-menus-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-menu-locations-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-users-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-comments-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-search-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-blocks-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-block-types-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-block-renderer-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-settings-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-themes-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-plugins-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-block-directory-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-edit-site-export-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-pattern-directory-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-block-patterns-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-block-pattern-categories-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-application-passwords-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-site-health-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-sidebars-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-widget-types-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-widgets-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-templates-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-url-details-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-navigation-fallback-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-font-families-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-font-faces-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-font-collections-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-icons-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-abilities-v1-categories-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-abilities-v1-list-controller.php';
require ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-abilities-v1-run-controller.php';
require ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-meta-fields.php';
require ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-comment-meta-fields.php';
require ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-post-meta-fields.php';
require ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-term-meta-fields.php';
require ABSPATH . WPINC . '/rest-api/fields/class-wp-rest-user-meta-fields.php';
require ABSPATH . WPINC . '/rest-api/search/class-wp-rest-search-handler.php';
require ABSPATH . WPINC . '/rest-api/search/class-wp-rest-post-search-handler.php';
require ABSPATH . WPINC . '/rest-api/search/class-wp-rest-term-search-handler.php';
require ABSPATH . WPINC . '/rest-api/search/class-wp-rest-post-format-search-handler.php';
require ABSPATH . WPINC . '/sitemaps.php';
require ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps.php';
require ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-index.php';
require ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-provider.php';
require ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-registry.php';
require ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-renderer.php';
require ABSPATH . WPINC . '/sitemaps/class-wp-sitemaps-stylesheet.php';
require ABSPATH . WPINC . '/sitemaps/providers/class-wp-sitemaps-posts.php';
require ABSPATH . WPINC . '/sitemaps/providers/class-wp-sitemaps-taxonomies.php';
require ABSPATH . WPINC . '/sitemaps/providers/class-wp-sitemaps-users.php';
require ABSPATH . WPINC . '/class-wp-block-bindings-source.php';
require ABSPATH . WPINC . '/class-wp-block-bindings-registry.php';
require ABSPATH . WPINC . '/class-wp-block-editor-context.php';
require ABSPATH . WPINC . '/class-wp-block-type.php';
require ABSPATH . WPINC . '/class-wp-block-pattern-categories-registry.php';
require ABSPATH . WPINC . '/class-wp-block-patterns-registry.php';
require ABSPATH . WPINC . '/class-wp-block-styles-registry.php';
require ABSPATH . WPINC . '/class-wp-block-type-registry.php';
require ABSPATH . WPINC . '/class-wp-block.php';
require ABSPATH . WPINC . '/class-wp-block-list.php';
require ABSPATH . WPINC . '/class-wp-block-metadata-registry.php';
require ABSPATH . WPINC . '/class-wp-block-parser-block.php';
require ABSPATH . WPINC . '/class-wp-block-parser-frame.php';
require ABSPATH . WPINC . '/class-wp-block-parser.php';
require ABSPATH . WPINC . '/class-wp-classic-to-block-menu-converter.php';
require ABSPATH . WPINC . '/class-wp-navigation-fallback.php';
require ABSPATH . WPINC . '/block-bindings.php';
require ABSPATH . WPINC . '/block-bindings/pattern-overrides.php';
require ABSPATH . WPINC . '/block-bindings/post-data.php';
require ABSPATH . WPINC . '/block-bindings/post-meta.php';
require ABSPATH . WPINC . '/block-bindings/term-data.php';
require ABSPATH . WPINC . '/blocks.php';
require ABSPATH . WPINC . '/blocks/index.php';
require ABSPATH . WPINC . '/block-editor.php';
require ABSPATH . WPINC . '/block-patterns.php';
require ABSPATH . WPINC . '/class-wp-block-supports.php';
require ABSPATH . WPINC . '/block-supports/utils.php';
require ABSPATH . WPINC . '/block-supports/align.php';
require ABSPATH . WPINC . '/block-supports/auto-register.php';
require ABSPATH . WPINC . '/block-supports/custom-classname.php';
require ABSPATH . WPINC . '/block-supports/generated-classname.php';
require ABSPATH . WPINC . '/block-supports/settings.php';
require ABSPATH . WPINC . '/block-supports/elements.php';
require ABSPATH . WPINC . '/block-supports/colors.php';
require ABSPATH . WPINC . '/block-supports/typography.php';
require ABSPATH . WPINC . '/block-supports/border.php';
require ABSPATH . WPINC . '/block-supports/layout.php';
require ABSPATH . WPINC . '/block-supports/position.php';
require ABSPATH . WPINC . '/block-supports/spacing.php';
require ABSPATH . WPINC . '/block-supports/dimensions.php';
require ABSPATH . WPINC . '/block-supports/duotone.php';
require ABSPATH . WPINC . '/block-supports/shadow.php';
require ABSPATH . WPINC . '/block-supports/background.php';
require ABSPATH . WPINC . '/block-supports/block-style-variations.php';
require ABSPATH . WPINC . '/block-supports/aria-label.php';
require ABSPATH . WPINC . '/block-supports/anchor.php';
require ABSPATH . WPINC . '/block-supports/block-visibility.php';
require ABSPATH . WPINC . '/block-supports/custom-css.php';
require ABSPATH . WPINC . '/style-engine.php';
require ABSPATH . WPINC . '/style-engine/class-wp-style-engine.php';
require ABSPATH . WPINC . '/style-engine/class-wp-style-engine-css-declarations.php';
require ABSPATH . WPINC . '/style-engine/class-wp-style-engine-css-rule.php';
require ABSPATH . WPINC . '/style-engine/class-wp-style-engine-css-rules-store.php';
require ABSPATH . WPINC . '/style-engine/class-wp-style-engine-processor.php';
require ABSPATH . WPINC . '/fonts/class-wp-font-face-resolver.php';
require ABSPATH . WPINC . '/fonts/class-wp-font-collection.php';
require ABSPATH . WPINC . '/fonts/class-wp-font-face.php';
require ABSPATH . WPINC . '/fonts/class-wp-font-library.php';
require ABSPATH . WPINC . '/fonts/class-wp-font-utils.php';
require ABSPATH . WPINC . '/fonts.php';
require ABSPATH . WPINC . '/class-wp-script-modules.php';
require ABSPATH . WPINC . '/script-modules.php';
require ABSPATH . WPINC . '/interactivity-api/class-wp-interactivity-api.php';
require ABSPATH . WPINC . '/interactivity-api/class-wp-interactivity-api-directives-processor.php';
require ABSPATH . WPINC . '/interactivity-api/interactivity-api.php';
require ABSPATH . WPINC . '/class-wp-plugin-dependencies.php';
require ABSPATH . WPINC . '/class-wp-url-pattern-prefixer.php';
require ABSPATH . WPINC . '/class-wp-speculation-rules.php';
require ABSPATH . WPINC . '/speculative-loading.php';
require ABSPATH . WPINC . '/view-transitions.php';

// SECTION: ===== Phase 15b — Script modules + interactivity hook registration (deferred to after_setup_theme) =====.
add_action( 'after_setup_theme', array( wp_script_modules(), 'add_hooks' ) );
add_action( 'after_setup_theme', array( wp_interactivity(), 'add_hooks' ) );

/**
 * @since 3.3.0
 *
 * @global WP_Embed $wp_embed WordPress Embed object.
 */
$GLOBALS['wp_embed'] = new WP_Embed();

/**
 * WordPress Textdomain Registry object.
 *
 * Used to support just-in-time translations for manually loaded text domains.
 *
 * @since 6.1.0
 *
 * @global WP_Textdomain_Registry $wp_textdomain_registry WordPress Textdomain Registry.
 */
$GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();
$GLOBALS['wp_textdomain_registry']->init();

// SECTION: ===== Phase 15c — AI client initialization =====.
// WordPress AI Client initialization.
// AI client three-step init: discovery strategy → cache adapter → event dispatcher. Each adapter lets WordPress integrate the upstream php-ai-client with WP_HTTP, wp_cache_*, and the WP hook system.
WP_AI_Client_Discovery_Strategy::init();
WordPress\AiClient\AiClient::setCache( new WP_AI_Client_Cache() );
WordPress\AiClient\AiClient::setEventDispatcher( new WP_AI_Client_Event_Dispatcher() );

// SECTION: ===== Phase 16 — Multisite-only requires (ms-functions, ms-default-filters, ms-deprecated) =====.
// Load multisite-specific files.
if ( is_multisite() ) {
	require ABSPATH . WPINC . '/ms-functions.php';
	require ABSPATH . WPINC . '/ms-default-filters.php';
	require ABSPATH . WPINC . '/ms-deprecated.php';
}

// SECTION: ===== Phase 16b — Plugin directory constants =====.
// Define constants that rely on the API to obtain the default value.
// Define must-use plugin directory constants, which may be overridden in the sunrise.php drop-in.
wp_plugin_directory_constants();

/**
 * @since 3.9.0
 *
 * @global array $wp_plugin_paths
 */
$GLOBALS['wp_plugin_paths'] = array();

// SECTION: ===== Phase 17 — MU plugins =====.
// Load must-use plugins.
// MU plugins (must-use) load before regular plugins; they cannot be deactivated from the admin.
foreach ( wp_get_mu_plugins() as $mu_plugin ) {
	$_wp_plugin_file = $mu_plugin;
	include_once $mu_plugin;
	// QUIRK: $_wp_plugin_file is a sentinel restored after the include because plugin code may have overwritten the loop variable.
	$mu_plugin = $_wp_plugin_file; // Avoid stomping of the $mu_plugin variable in a plugin.

	/**
	 * Fires once a single must-use plugin has loaded.
	 *
	 * @since 5.1.0
	 *
	 * @param string $mu_plugin Full path to the plugin's main file.
	 */
	do_action( 'mu_plugin_loaded', $mu_plugin );
}
unset( $mu_plugin, $_wp_plugin_file );

// SECTION: ===== Phase 18 — Network-activated plugins (multisite only) =====.
// Load network activated plugins.
if ( is_multisite() ) {
	foreach ( wp_get_active_network_plugins() as $network_plugin ) {
		wp_register_plugin_realpath( $network_plugin );

		$_wp_plugin_file = $network_plugin;
		include_once $network_plugin;
		$network_plugin = $_wp_plugin_file; // Avoid stomping of the $network_plugin variable in a plugin.

		/**
		 * Fires once a single network-activated plugin has loaded.
		 *
		 * @since 5.1.0
		 *
		 * @param string $network_plugin Full path to the plugin's main file.
		 */
		do_action( 'network_plugin_loaded', $network_plugin );
	}
	unset( $network_plugin, $_wp_plugin_file );
}

// SECTION: ===== Phase 19 — muplugins_loaded action =====.
/**
 * Fires once all must-use and network-activated plugins have loaded.
 *
 * @since 2.8.0
 */
do_action( 'muplugins_loaded' );

// SECTION: ===== Phase 20 — Cookie + SSL constants =====.
if ( is_multisite() ) {
	ms_cookie_constants();
}

// Define constants after multisite is loaded.
wp_cookie_constants();

// Define and enforce our SSL constants.
wp_ssl_constants();

// SECTION: ===== Phase 21 — Globals + initial taxonomies/post-types =====.
// Create common globals.
require ABSPATH . WPINC . '/vars.php';

// Make taxonomies and posts available to plugins and themes.
// @plugin authors: warning: these get registered again on the init hook.
// QUIRK: WordPress registers built-in CPTs/taxonomies twice — once here for early access, again on 'init' so plugins can filter the registration args.
create_initial_taxonomies();
create_initial_post_types();

wp_start_scraping_edited_file_errors();

// SECTION: ===== Phase 22 — Theme directory registration =====.
// Register the default theme directory root.
register_theme_directory( get_theme_root() );

// SECTION: ===== Phase 23 — Recovery mode init (single-site only) =====.
if ( ! is_multisite() && wp_is_fatal_error_handler_enabled() ) {
	// Handle users requesting a recovery mode link and initiating recovery mode.
	wp_recovery_mode()->initialize();
}

// SECTION: ===== Phase 23b — Plugin admin includes (early require for #62244) =====.
// To make get_plugin_data() available in a way that's compatible with plugins also loading this file, see #62244.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// SECTION: ===== Phase 24 — Active plugin loader =====.
// Load active plugins.
foreach ( wp_get_active_and_valid_plugins() as $plugin ) {
	wp_register_plugin_realpath( $plugin );

	$plugin_data = get_plugin_data( $plugin, false, false );

	// Register each plugin's text domain BEFORE the plugin loads so plugin code calling __() during inclusion gets translations.
	$textdomain = $plugin_data['TextDomain'];
	if ( $textdomain ) {
		if ( $plugin_data['DomainPath'] ) {
			$GLOBALS['wp_textdomain_registry']->set_custom_path( $textdomain, dirname( $plugin ) . $plugin_data['DomainPath'] );
		} else {
			$GLOBALS['wp_textdomain_registry']->set_custom_path( $textdomain, dirname( $plugin ) );
		}
	}

	$_wp_plugin_file = $plugin;
	include_once $plugin;
	$plugin = $_wp_plugin_file; // Avoid stomping of the $plugin variable in a plugin.

	/**
	 * Fires once a single activated plugin has loaded.
	 *
	 * @since 5.1.0
	 *
	 * @param string $plugin Full path to the plugin's main file.
	 */
	do_action( 'plugin_loaded', $plugin );
}
unset( $plugin, $_wp_plugin_file, $plugin_data, $textdomain );

// SECTION: ===== Phase 25 — Pluggable functions =====.
// Load pluggable functions.
require ABSPATH . WPINC . '/pluggable.php';
require ABSPATH . WPINC . '/pluggable-deprecated.php';

// SECTION: ===== Phase 26 — Internal encoding =====.
// Set internal encoding.
// Sets mbstring.internal_encoding to UTF-8 if mbstring extension is available; affects str_replace, regex, etc.
wp_set_internal_encoding();

// SECTION: ===== Phase 26b — Object cache postload =====.
// Run wp_cache_postload() if object cache is enabled and the function exists.
if ( WP_CACHE && function_exists( 'wp_cache_postload' ) ) {
	wp_cache_postload();
}

// SECTION: ===== Phase 27 — plugins_loaded action =====.
/**
 * Fires once activated plugins have loaded.
 *
 * Pluggable functions are also available at this point in the loading order.
 *
 * @since 1.5.0
 */
do_action( 'plugins_loaded' );

// SECTION: ===== Phase 28 — Functionality constants + magic quotes =====.
// Define constants which affect functionality if not already defined.
wp_functionality_constants();

// QUIRK: WordPress emulates the historical magic_quotes_gpc behavior in $_GET/$_POST/$_REQUEST/$_COOKIE because legacy code (and many plugins) assume slashes are present. This is the source of the persistent wp_unslash() pattern across the codebase.
// Add magic quotes and set up $_REQUEST ( $_GET + $_POST ).
wp_magic_quotes();

// SECTION: ===== Phase 29 — sanitize_comment_cookies action =====.
/**
 * Fires when comment cookies are sanitized.
 *
 * @since 2.0.11
 */
do_action( 'sanitize_comment_cookies' );

// SECTION: ===== Phase 30 — Core globals (wp_query, wp_rewrite, wp, wp_widget_factory, wp_roles) =====.
/**
 * WordPress Query object
 *
 * @since 2.0.0
 *
 * @global WP_Query $wp_the_query WordPress Query object.
 */
$GLOBALS['wp_the_query'] = new WP_Query();

/**
 * Holds the reference to {@see $wp_the_query}.
 * Use this global for WordPress queries
 *
 * @since 1.5.0
 *
 * @global WP_Query $wp_query WordPress Query object.
 */
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];

/**
 * Holds the WordPress Rewrite object for creating pretty URLs
 *
 * @since 1.5.0
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 */
$GLOBALS['wp_rewrite'] = new WP_Rewrite();

/**
 * WordPress Object
 *
 * @since 2.0.0
 *
 * @global WP $wp Current WordPress environment instance.
 */
$GLOBALS['wp'] = new WP();

/**
 * WordPress Widget Factory Object
 *
 * @since 2.8.0
 *
 * @global WP_Widget_Factory $wp_widget_factory
 */
$GLOBALS['wp_widget_factory'] = new WP_Widget_Factory();

/**
 * WordPress User Roles
 *
 * @since 2.0.0
 *
 * @global WP_Roles $wp_roles WordPress role management object.
 */
$GLOBALS['wp_roles'] = new WP_Roles();

// SECTION: ===== Phase 31 — setup_theme action =====.
/**
 * Fires before the theme is loaded.
 *
 * @since 2.6.0
 */
do_action( 'setup_theme' );

// SECTION: ===== Phase 32 — Templating constants + locale =====.
// Define the template related constants and globals.
wp_templating_constants();
wp_set_template_globals();

// Load the default text localization domain.
load_default_textdomain();

$locale      = get_locale();
$locale_file = WP_LANG_DIR . "/$locale.php";
if ( ( 0 === validate_file( $locale ) ) && is_readable( $locale_file ) ) {
	require $locale_file;
}
unset( $locale_file );

/**
 * WordPress Locale object for loading locale domain date and various strings.
 *
 * @since 2.1.0
 *
 * @global WP_Locale $wp_locale WordPress date and time locale object.
 */
$GLOBALS['wp_locale'] = new WP_Locale();

/**
 * WordPress Locale Switcher object for switching locales.
 *
 * @since 4.7.0
 *
 * @global WP_Locale_Switcher $wp_locale_switcher WordPress locale switcher object.
 */
$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
$GLOBALS['wp_locale_switcher']->init();

// SECTION: ===== Phase 33 — Theme functions.php loading (parent + child) =====.
// Load the functions for the active theme, for both parent and child theme if applicable.
foreach ( wp_get_active_and_valid_themes() as $theme ) {
	$wp_theme = wp_get_theme( basename( $theme ) );

	$wp_theme->load_textdomain();

	if ( file_exists( $theme . '/functions.php' ) ) {
		include $theme . '/functions.php';
	}
}
unset( $theme, $wp_theme );

// SECTION: ===== Phase 34 — after_setup_theme action =====.
/**
 * Fires after the theme is loaded.
 *
 * @since 3.0.0
 */
do_action( 'after_setup_theme' );

// SECTION: ===== Phase 35 — Site Health init for cron =====.
// Create an instance of WP_Site_Health so that Cron events may fire.
if ( ! class_exists( 'WP_Site_Health' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
}
WP_Site_Health::get_instance();

// SECTION: ===== Phase 36 — Current user setup =====.
// Set up current user.
// WP::init() resolves the current user via authentication cookies; subsequent code can call wp_get_current_user().
$GLOBALS['wp']->init();

// SECTION: ===== Phase 37 — init action (most plugins/widgets register here) =====.
/**
 * Fires after WordPress has finished loading but before any headers are sent.
 *
 * Most of WP is loaded at this stage, and the user is authenticated. WP continues
 * to load on the {@see 'init'} hook that follows (e.g. widgets), and many plugins instantiate
 * themselves on it for all sorts of reasons (e.g. they need a user, a taxonomy, etc.).
 *
 * If you wish to plug an action once WP is loaded, use the {@see 'wp_loaded'} hook below.
 *
 * @since 1.5.0
 */
do_action( 'init' );

// SECTION: ===== Phase 38 — Multisite site check (archived/deleted/banned guard) =====.
// Check site status.
// FRAGILE: ms_site_check() returns the path to a wp-content/blog-deleted.php / blog-suspended.php / blog-archived.php template if the site is in a non-active state; including it terminates the request with that template.
if ( is_multisite() ) {
	$file = ms_site_check();
	if ( true !== $file ) {
		require $file;
		die();
	}
	unset( $file );
}

// SECTION: ===== Phase 39 — wp_loaded action (final bootstrap signal) =====.
/**
 * This hook is fired once WP, all plugins, and the theme are fully loaded and instantiated.
 *
 * Ajax requests should use wp-admin/admin-ajax.php. admin-ajax.php can handle requests for
 * users not logged in.
 *
 * @link https://developer.wordpress.org/plugins/javascript/ajax
 *
 * @since 3.0.0
 */
do_action( 'wp_loaded' );
