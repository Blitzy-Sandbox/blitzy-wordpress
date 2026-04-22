# Functional Specification (FS)

> **Document:** FS-WP70-PERF-001
> **Version:** 1.0
> **Status:** Frozen (required before OQ execution)
> **Freeze Date:** 2026-04-22
> **V-Model Position:** Left middle (paired with OQ on the right)
> **Parent:** `URS.md`

---

## 1. Purpose

This Functional Specification (FS) describes **how** each user requirement from `URS.md` is realised in WordPress 7.0 code paths. Each FS row is a **functional behaviour** — a testable statement about system behaviour, independent of line-level design.

A single URS may be satisfied by multiple FSs (fan-out). Every FS MUST forward-trace to ≥ 1 DS and reverse-trace to exactly 1 URS.

---

## 2. Functional Specifications

### 2.1 PHP Runtime Bootstrap

| FS ID | Parent URS | Functional Statement | OQ Verification |
|-------|-----------|----------------------|-----------------|
| FS-001 | URS-001, URS-004, URS-006 | The WordPress bootstrap (`wp-settings.php`) SHALL conditionally load subsystem files based on request type, such that non-essential files are deferred until their subsystem is invoked. | OQ-101 checks file count on front-end vs. admin vs. REST requests; tests for `is_admin()`, REST detection, AJAX detection guards. |
| FS-002 | URS-001 | `WP_Hook::apply_filters()` SHALL dispatch single-callback, single-argument hooks via a direct-invocation fast path (`$callback($value)`) rather than `call_user_func_array`. | OQ-102 runs `Tests_Hooks_*` and `Tests_Filters` / `Tests_Actions` suites with 0 new failures; inspects `class-wp-hook.php` for fast-path branch. |
| FS-003 | URS-005 | `get_option()` SHALL memoize repeated lookups within a request to avoid redundant `wp_load_alloptions()` invocations. | OQ-103 runs `Tests_Option` suite; verifies no new failures. |
| FS-004 | URS-001, URS-005 | Hot-path utility functions in `functions.php` and `formatting.php` (e.g., `wp_parse_args`, `esc_html`, `esc_attr`, `sanitize_text_field`, regex precompilation in `wpautop`/`wptexturize`) SHALL have reduced per-call overhead. | OQ-104 runs `Tests_Functions_*` and `Tests_Formatting_*` suites with 0 new failures. |

### 2.2 Database & Query Layer

| FS ID | Parent URS | Functional Statement | OQ Verification |
|-------|-----------|----------------------|-----------------|
| FS-010 | URS-005 | `WP_Query::get_posts()` SHALL skip JOIN/subquery construction for simple single-post-type queries lacking meta/tax clauses. | OQ-110 runs `Tests_Query` suite; verifies `fill_query_vars` / SQL generation remain correct. |
| FS-011 | URS-005 | `WP_Meta_Query::get_sql()` SHALL use `EXISTS` subqueries instead of `LEFT JOIN` for single-key existence-only clauses. | OQ-111 runs `Tests_Meta_Query` test cases. |
| FS-012 | URS-005 | `wpdb::prepare()` SHALL cache the sprintf result of identical prepared statements within a single request. | OQ-112 runs `Tests_DB_Prepare` test cases. |
| FS-013 | URS-005 | `update_meta_cache()` and allied helpers SHALL expose batch-priming functions (`wp_prime_meta_caches`, expanded `WP_Metadata_Lazyloader`) covering post, term, comment, and user meta types. | OQ-113 runs `Tests_Meta` / `Tests_Meta_Lazyloader` suites. |

### 2.3 Object Cache

| FS ID | Parent URS | Functional Statement | OQ Verification |
|-------|-----------|----------------------|-----------------|
| FS-020 | URS-005 | `WP_Object_Cache` SHALL expose per-group `cache_hits`/`cache_misses` counters consumable by instrumentation. | OQ-120 runs `Tests_Cache` suite; inspects `class-wp-object-cache.php` for counter fields. |
| FS-021 | URS-005 | Cache invalidation helpers SHALL invalidate at key granularity, not group granularity, for autoloaded option updates. | OQ-121 verifies no new failures in `Tests_Cache` / `Tests_Option` after invalidation refactor. |

### 2.4 REST API Serialization

| FS ID | Parent URS | Functional Statement | OQ Verification |
|-------|-----------|----------------------|-----------------|
| FS-030 | URS-007 | `WP_REST_Posts_Controller::get_items()` SHALL call `update_postmeta_cache()` + `update_object_term_cache()` + attachment cache priming before the serialization loop. | OQ-130 runs `WP_REST_Posts_Controller_Test`. |
| FS-031 | URS-007 | `WP_REST_Comments_Controller::get_items()` SHALL batch-prime comment meta and author user caches. | OQ-131 runs `WP_REST_Comments_Controller_Test`. |
| FS-032 | URS-007 | `WP_REST_Terms_Controller::get_items()` SHALL batch-prime term meta caches. | OQ-132 runs `WP_REST_Terms_Controller_Test`. |
| FS-033 | URS-007 | `WP_REST_Users_Controller::get_items()` SHALL batch-prime user meta caches. | OQ-133 runs `WP_REST_Users_Controller_Test`. |
| FS-034 | URS-007 | `WP_REST_Attachments_Controller::get_items()` SHALL batch-prime attachment meta + terms. | OQ-134 runs `WP_REST_Attachments_Controller_Test`. |
| FS-035 | URS-007 | Revisions, autosaves, global-styles-revisions, templates, and search controllers SHALL batch-prime before serialization. | OQ-135 runs corresponding tests + verifies `rest-controller-audit.csv` categorizations. |

### 2.5 Script & Style Loading

| FS ID | Parent URS | Functional Statement | OQ Verification |
|-------|-----------|----------------------|-----------------|
| FS-040 | URS-003 | `_print_emoji_detection_script()` SHALL exit early on modern browsers without invoking full detection logic, and SHALL be subject to deferred enqueuing. | OQ-140 runs `Tests_Emoji` QUnit tests (if present) + verifies `emoji-loader.js` guard branches. |
| FS-041 | URS-003 | Customizer JS bundles (`controls.js`, `nav-menus.js`, `widgets.js`) SHALL remain restricted to the Customizer context. | OQ-141 verifies hook guards in `customize_register`/`customize_controls_init`. |
| FS-042 | URS-003 | Admin `common.js` SHALL conditionally initialise screen-specific modules based on `adminpage`/`pagenow` globals. | OQ-142 runs QUnit admin tests. |

### 2.6 Observability

| FS ID | Parent URS | Functional Statement | OQ Verification |
|-------|-----------|----------------------|-----------------|
| FS-050 | URS-010 | `server-timing.php` mu-plugin SHALL emit `wp-bootstrap`, `wp-plugins`, `wp-files-loaded`, `wp-cache-hits`, `wp-cache-misses` Server-Timing entries. | OQ-150 inspects `tests/performance/wp-content/mu-plugins/server-timing.php` for the five new metrics. |
| FS-051 | URS-010 | Server-Timing headers SHALL NOT be emitted after `headers_sent()` returns true (hardening against fatal errors). | OQ-151 inspects the guard clause in the shutdown callback. |

### 2.7 Decision & Traceability

| FS ID | Parent URS | Functional Statement | OQ Verification |
|-------|-----------|----------------------|-----------------|
| FS-060 | URS-011, URS-012 | A Decision Log SHALL exist covering each optimization area with rationale + alternatives + outcome. | OQ-160 inspects `../decision-log-and-traceability.md` Section 1. |
| FS-061 | URS-012 | A bidirectional RTM SHALL exist with zero orphan rows in both directions. | OQ-161 inspects `../decision-log-and-traceability.md` Sections 2–4 and `RTM-bidirectional.md`. |

### 2.8 Quality & Compliance

| FS ID | Parent URS | Functional Statement | OQ Verification |
|-------|-----------|----------------------|-----------------|
| FS-070 | URS-008 | PHPUnit `default` testsuite SHALL report ≤ 3 errors + ≤ 4 failures (matching documented pre-existing PHP 8.3 timezone baseline). | OQ-170 runs `vendor/bin/phpunit --testsuite default` and diffs vs. baseline. |
| FS-071 | URS-008 | PHPUnit `restapi-autosave` suite SHALL report 0 failures/errors. | OQ-171 runs `vendor/bin/phpunit --testsuite restapi-autosave`. |
| FS-072 | URS-008 | QUnit SHALL report 0 failures across 456 tests. | OQ-172 runs `CHROMIUM_FLAGS='--no-sandbox' grunt qunit`. |
| FS-073 | URS-009 | Public method signatures for the APIs enumerated in URS-009 SHALL remain unchanged from the pre-optimization branch-point. | OQ-173 runs a static diff on the named files. |
| FS-074 | URS-013 | Every metric referenced in PQ SHALL have an ICH Q9 classification. | OQ-174 inspects `ICH-Q9-risk-classification.md`. |
| FS-075 | URS-014 | Every "Insufficient signal" event SHALL be logged in `deviation-register.md` with full disposition fields. | OQ-175 inspects `deviation-register.md`. |
| FS-076 | URS-015 | Each ALCOA+ principle SHALL have ≥ 1 evidence pointer. | OQ-176 inspects `ALCOA-plus-compliance.md`. |
| FS-077 | URS-016 | Each GAMP 5 Category 5 validation gate SHALL record a binary PASS/FAIL. | OQ-177 inspects `GAMP5-category5-gates.md`. |

---

## 3. Freeze Control

Frozen at the timestamp in the document header. Any change requires a new version and a change-control entry in `signoff-record.md`.

**Freeze Attestation:** All FSs in §2 are authored, reverse-traced to URSs, and frozen for OQ execution.
