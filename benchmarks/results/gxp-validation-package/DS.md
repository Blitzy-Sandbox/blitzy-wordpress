# Design Specification (DS)

> **Document:** DS-WP70-PERF-001
> **Version:** 1.0
> **Status:** Frozen (required before IQ execution)
> **Freeze Date:** 2026-04-22
> **V-Model Position:** Left bottom (paired with IQ on the right)
> **Parents:** `URS.md`, `FS.md`

---

## 1. Purpose

This Design Specification (DS) pins each Functional Specification to **concrete file(s), line-of-code patterns, and code-review-verifiable designs**. Each DS row is an **implementation-level contract** — it states which file, which method or block, and the nature of the change.

Every DS MUST forward-trace to ≥ 1 Implementation commit and reverse-trace to exactly 1 FS.

All files referenced below are **in-scope per AAP §0.3.1** (Exhaustively In Scope).

---

## 2. Design Specifications

### 2.1 PHP Runtime Bootstrap Designs

| DS ID | Parent FS | File(s) | Design Detail |
|-------|-----------|---------|--------------|
| DS-001 | FS-001 | `src/wp-settings.php` | Introduce request-type guards (`is_admin()`, REST detection via `WP_REST_REQUEST`, AJAX detection, Cron detection) and wrap block-editor, REST endpoint, AI, collaboration, abilities, connectors, and interactivity require blocks in context-aware conditionals. Preserve deterministic load order for remaining eager requires. |
| DS-002 | FS-001 | `src/wp-load.php` | Add OPcache preload hints where available; optimize config-file discovery walk. Preserve legacy path fall-through. |
| DS-003 | FS-001 | `src/wp-blog-header.php` | Add Server-Timing observability marker + OPcache precompile hint. |
| DS-004 | FS-002 | `src/wp-includes/class-wp-hook.php` | In `apply_filters()`/`do_action()`, add a fast path: when `$the_['accepted_args'] === 1` and callback is a closure or string, invoke `$callback($args[0])` directly, bypassing `call_user_func_array`. Preserve the 0-arg and slice branches. |
| DS-005 | FS-002 | `src/wp-includes/plugin.php` | In `apply_filters()`/`do_action()` wrappers, short-circuit when `! isset( $wp_filter[ $hook_name ] )`. Memoize `_wp_filter_build_unique_id()` for common closure/string cases. |
| DS-006 | FS-003 | `src/wp-includes/option.php` | Add `has_filter()` guards around non-essential filter invocations in `get_option()`; improve `notoptions` cache coherency; batch option priming for known option groups. |
| DS-007 | FS-001 | `src/wp-includes/load.php` | Reduce allocation in `wp_fix_server_vars()`, `timer_start()`, and `wp_check_php_mysql_versions()`. |
| DS-008 | FS-004 | `src/wp-includes/functions.php` | Optimize `wp_parse_args()`, `wp_list_pluck()`, `wp_json_encode()`, `wp_slash()`/`wp_unslash()` for type-specific fast paths. |
| DS-009 | FS-004 | `src/wp-includes/formatting.php` | Precompile frequently used regex patterns via static caching; fast-path `esc_html()`/`esc_attr()` for ASCII-only input; reduce allocation in `wpautop()`, `wptexturize()`. |
| DS-010 | FS-001, FS-002 | `src/wp-includes/default-filters.php` | Defer admin-only and emoji-only hook registrations behind `is_admin()` and emoji-content guards where safe. |
| DS-011 | FS-001 | `src/wp-includes/default-constants.php` | Minor allocation reductions; no behavior change. |

### 2.2 Database & Query Layer Designs

| DS ID | Parent FS | File(s) | Design Detail |
|-------|-----------|---------|--------------|
| DS-020 | FS-010 | `src/wp-includes/class-wp-query.php` | Add fast path in `get_posts()` bypassing JOIN construction when query vars contain no meta/tax clauses; cache `fill_query_vars` defaults; thumbnail cache priming. |
| DS-021 | FS-010 | `src/wp-includes/class-wp-tax-query.php` | Single-taxonomy fast path avoiding array merge. |
| DS-022 | FS-010 | `src/wp-includes/class-wp-date-query.php` | Index-friendly SQL for common date clauses; avoid `DATE()` wrapping when using range. |
| DS-023 | FS-010 | `src/wp-includes/class-wp-comment-query.php` | Eager batch-prime comment meta and author user caches. |
| DS-024 | FS-010 | `src/wp-includes/class-wp-term-query.php` | Batch term meta priming; single-taxonomy SQL. |
| DS-025 | FS-010 | `src/wp-includes/class-wp-user-query.php` | Batch user meta priming; capability resolution caching; meta key caching. |
| DS-026 | FS-011 | `src/wp-includes/class-wp-meta-query.php` | Replace `LEFT JOIN` with `EXISTS` for single-key existence clauses; cache cast lookups. |
| DS-027 | FS-012 | `src/wp-includes/class-wpdb.php` | Cache `prepare()` sprintf results within request; fast-path `_real_escape()`. |
| DS-028 | FS-013 | `src/wp-includes/meta.php` | Add `wp_prime_meta_caches()`; optimize `update_meta_cache()` hot path. |
| DS-029 | FS-013 | `src/wp-includes/class-wp-metadata-lazyloader.php` | Expand `WP_Metadata_Lazyloader` to cover `post` and `user` meta types. |
| DS-030 | FS-003 | `src/wp-includes/query.php` | Cache conditional-tag results. |

### 2.3 Object Cache Designs

| DS ID | Parent FS | File(s) | Design Detail |
|-------|-----------|---------|--------------|
| DS-040 | FS-020 | `src/wp-includes/class-wp-object-cache.php` | Add `cache_hits` / `cache_misses` integer counters (already present in class; extend to per-group map); optimize `get_multiple()` / `set_multiple()`. |
| DS-041 | FS-021 | `src/wp-includes/cache.php` | Add cache priming helpers (`wp_cache_get_multiple`-optimized paths); fast-path `wp_cache_get()` miss/hit accounting. |
| DS-042 | FS-020 | `src/wp-includes/cache-compat.php` | Static caching, empty guards, consolidated `isset`. |

### 2.4 Template Tags Designs

| DS ID | Parent FS | File(s) | Design Detail |
|-------|-----------|---------|--------------|
| DS-050 | FS-003 | `src/wp-includes/post.php` | Fast-path `get_post()` cache hit; memoize `get_post_ancestors()`; cached `get_post_type()`. |
| DS-051 | FS-003 | `src/wp-includes/post-template.php` | Request-level cache for hot template functions. |
| DS-052 | FS-003 | `src/wp-includes/taxonomy.php` | Memoize `get_object_taxonomies()`; optimize `is_taxonomy_hierarchical()` and `get_object_term_cache()`. |
| DS-053 | FS-003 | `src/wp-includes/comment.php` | Batch-prime comment meta + author user caches; request-level static cache for `wp_count_comments()`. |
| DS-054 | FS-003 | `src/wp-includes/comment-template.php` | Batch-prime comment meta and user caches in `wp_list_comments()`. |
| DS-055 | FS-003 | `src/wp-includes/user.php` | Per-request capability cache; batch user meta priming. |
| DS-056 | FS-003 | `src/wp-includes/capabilities.php` | Memoize `map_meta_cap()` for repeated checks on same user/post. |
| DS-057 | FS-003 | `src/wp-includes/media.php` | Per-request static cache + batch attachment cache priming. |
| DS-058 | FS-003 | `src/wp-includes/link-template.php` | Request-level `get_permalink()` cache. |
| DS-059 | FS-003 | `src/wp-includes/general-template.php` | Request-level cache for `get_bloginfo()` and other hot-path template tag functions. |
| DS-060 | FS-003 | `src/wp-includes/nav-menu.php` | Batch-prime menu item meta; request-level static cache. |
| DS-061 | FS-003 | `src/wp-includes/author-template.php` | Request-level caching and batch priming. |

### 2.5 REST API Designs

| DS ID | Parent FS | File(s) | Design Detail |
|-------|-----------|---------|--------------|
| DS-070 | FS-030 | `src/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php` | Call `_prime_post_caches()` (or `update_postmeta_cache` + `update_object_term_cache` + attachment priming) before `prepare_item_for_response()` loop in `get_items()`. |
| DS-071 | FS-031 | `src/wp-includes/rest-api/endpoints/class-wp-rest-comments-controller.php` | Batch-prime comment meta + author user caches in `get_items()`. |
| DS-072 | FS-032 | `src/wp-includes/rest-api/endpoints/class-wp-rest-terms-controller.php` | Batch-prime term meta in `get_items()`. |
| DS-073 | FS-033 | `src/wp-includes/rest-api/endpoints/class-wp-rest-users-controller.php` | Batch-prime user meta in `get_items()`. |
| DS-074 | FS-034 | `src/wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php` | Batch-prime attachment meta + term caches; safety guards in `rest-api.php`. |
| DS-075 | FS-035 | `src/wp-includes/rest-api/endpoints/class-wp-rest-revisions-controller.php` + `class-wp-rest-autosaves-controller.php` + `class-wp-rest-global-styles-revisions-controller.php` + `class-wp-rest-templates-controller.php` + `class-wp-rest-search-controller.php` | Each `get_items()` calls `update_postmeta_cache()` or `_prime_post_caches()` before serialization. Verified by `rest-controller-audit.csv`. |
| DS-076 | FS-030 | `src/wp-includes/rest-api.php` | Normalize trailing slashes in preload fast path; batch entity cache priming at REST infrastructure level. |

### 2.6 Script & Style Loading Designs

| DS ID | Parent FS | File(s) | Design Detail |
|-------|-----------|---------|--------------|
| DS-080 | FS-040 | `src/js/_enqueues/lib/emoji-loader.js` | Multi-tier early exits: (1) `navigator.userAgent` quick test, (2) `canvas` feature test, (3) worker-offloaded decode; declare `_wpemojiSettings` global. |
| DS-081 | FS-040 | `src/js/_enqueues/wp/emoji.js` | Lazy init; early exit for native browser support; remove IE11-only code. |
| DS-082 | FS-041 | `src/js/_enqueues/wp/customize/controls.js` + `nav-menus.js` + `widgets.js` | Add deferred-init guards; only run heavy initialization when Customizer pane is open. |
| DS-083 | FS-042 | `src/js/_enqueues/admin/common.js` | Screen-specific init gated by `pagenow`/`adminpage` globals. |
| DS-084 | FS-040, FS-041 | `src/wp-includes/script-loader.php` | Conditional emoji enqueue; Customizer scripts only enqueued in Customizer context; optimize `wp_default_scripts()` / `wp_default_styles()`. |
| DS-085 | FS-041 | `src/wp-includes/class-wp-scripts.php` + `class-wp-styles.php` + `class-wp-dependencies.php` + `functions.wp-scripts.php` + `class-wp-script-modules.php` | Cache resolved dependency chains; pre-compute escaped attributes in `do_item()`; memoize script helper lookups. |

### 2.7 Admin PHP Designs

| DS ID | Parent FS | File(s) | Design Detail |
|-------|-----------|---------|--------------|
| DS-090 | FS-042 | `src/wp-admin/admin.php` | AJAX fast path; transient-gated cron; screen-specific script hook. |
| DS-091 | FS-042 | `src/wp-admin/admin-header.php` | Conditional assets; reduced allocations. |
| DS-092 | FS-042 | `src/wp-admin/includes/ajax-actions.php` | Conditional handler loading (only define the handler matching `$_REQUEST['action']`). |
| DS-093 | FS-042 | `src/wp-admin/load-scripts.php` + `load-styles.php` | Optimize concatenation endpoints. |

### 2.8 Observability Designs

| DS ID | Parent FS | File(s) | Design Detail |
|-------|-----------|---------|--------------|
| DS-100 | FS-050 | `tests/performance/wp-content/mu-plugins/server-timing.php` | Emit `bootstrap`, `plugins`, `files-loaded`, `cache-hits`, `cache-misses` Server-Timing entries in addition to existing `before-template`, `template`, `total`, `memory-usage`, `db-queries`, `ext-obj-cache`. |
| DS-101 | FS-051 | `tests/performance/wp-content/mu-plugins/server-timing.php` | Guard `header()` calls behind `! headers_sent()` to avoid fatal errors after opcache_reset-triggered output. |
| DS-102 | FS-050 | `tests/performance/specs/admin.test.js` + `home.test.js` + `single-post.test.js` | Collect new Server-Timing entries in Playwright; add `jsTransferSize` and `domContentLoaded` metrics. |
| DS-103 | FS-050 | `tests/performance/utils.js` + `tests/performance/compare-results.js` | Add formatters for `jsTransferSize` / `cache-hits` / `cache-misses` / `files-loaded`; extend comparison output. |

### 2.9 Build System Designs

| DS ID | Parent FS | File(s) | Design Detail |
|-------|-----------|---------|--------------|
| DS-110 | FS-042 | `Gruntfile.js` | Build-pipeline documentation + optimized concat/uglify tasks. |
| DS-111 | FS-040, FS-041 | `webpack.config.js` + `tools/webpack/media.js` + `tools/webpack/development.js` | Code-splitting env-configured entry points for conditional module loading. |

### 2.10 Harness Designs

| DS ID | Parent FS | File(s) | Design Detail |
|-------|-----------|---------|--------------|
| DS-120 | FS-050 | `benchmarks/run-benchmark.js` + `run-baseline.sh` + `run-optimized.sh` + `generate-diff-report.js` + `docker-compose.benchmark.yml` | Containerized benchmark harness with 3 runs × 5 iterations, JSON output, diff reporting. |

---

## 3. Freeze Control

Frozen at the timestamp in the document header. Any change requires a new version and a change-control entry in `signoff-record.md`.

**Freeze Attestation:** All DSs in §2 are authored, reverse-traced to FSs, and frozen for IQ execution.
