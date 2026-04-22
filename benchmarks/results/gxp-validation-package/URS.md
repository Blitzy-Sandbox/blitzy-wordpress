# User Requirements Specification (URS)

> **Document:** URS-WP70-PERF-001
> **Version:** 1.0
> **Status:** Frozen (required before PQ execution)
> **Freeze Date:** 2026-04-22
> **V-Model Position:** Left top (paired with PQ on the right)

---

## 1. Purpose

This URS captures the **user-verifiable requirements** for the WordPress 7.0 performance optimization refactoring, derived from:

- The Agent Action Plan (AAP) §0.4.4 *Performance Target Architecture* and §0.8.4 *Performance Targets*.
- The user's Refine PR directive requiring GxP-grade qualification evidence.
- Downstream stakeholder expectations for WordPress hosts, site operators, and end users.

Each URS is the **root node** of a forward trace that descends through FS → DS → Implementation → IQ → OQ → PQ. Each URS MUST appear in the forward direction of `RTM-bidirectional.md` with at least one downstream artifact and at least one measured result.

---

## 2. Stakeholders

| Role | Interest |
|------|----------|
| WordPress end user | Faster page loads on WordPress-powered sites (43 %+ of the web). |
| WordPress site operator | Lower CPU/memory/bandwidth costs; better Core Web Vitals; improved SEO. |
| WordPress core maintainer | Backward-compatible optimizations that preserve all public APIs and tests. |
| WordPress plugin/theme author | Stable hook contracts, dependency graphs, and JS globals. |
| Regulatory auditor (surrogate) | Evidence of disciplined, measurement-driven engineering. |

---

## 3. User Requirements

Each requirement is of the form `URS-NNN`. Requirements are **verifiable** (testable with a defined method) and **singular** (one requirement per row).

| URS ID | Requirement Statement | Verification Method | Target | Acceptance Criterion |
|--------|----------------------|---------------------|--------|---------------------|
| URS-001 | Uncached front-end Time To First Byte (TTFB) SHALL be reduced versus baseline. | Benchmark harness `benchmarks/run-benchmark.js` — median TTFB across 3 runs × 5 iterations. | ≥ 20 % reduction | PQ records `ttfb_delta_pct ≥ 20` in `../benchmark-report.json`. |
| URS-002 | Admin page DOMContentLoaded SHALL be reduced versus baseline. | Benchmark harness `benchmarks/run-benchmark.js` — median DCL across 3 runs × 5 iterations. | ≥ 15 % reduction | PQ records `dom_content_loaded_delta_pct ≥ 15` in `../benchmark-report.json`. |
| URS-003 | Admin JavaScript transfer size (gzipped) SHALL be reduced versus baseline. | Grunt build output size analysis, transferred-size instrumentation in `tests/performance/specs/admin.test.js`. | ≥ 30 % reduction (AAP target) | PQ records either a measured reduction or an explicit "Insufficient signal" deviation with full disposition. |
| URS-004 | PHP peak memory usage per front-end request SHALL be reduced versus baseline. | Server-Timing `memory-usage` metric emitted by `tests/performance/wp-content/mu-plugins/server-timing.php`. | ≥ 10 % reduction (AAP target) | PQ records measured reduction or "Insufficient signal" deviation. |
| URS-005 | Database queries per front-end page load SHALL be reduced versus baseline. | Server-Timing `db-queries` metric sourced from `$wpdb->num_queries`. | ≥ 15 % reduction (AAP target) | PQ records measured reduction or "Insufficient signal" deviation. |
| URS-006 | PHP files loaded per front-end request SHALL be reduced versus baseline. | Server-Timing `files-loaded` metric sourced from `count( get_included_files() )`. | ≥ 30 % reduction (AAP target) | PQ records measured reduction or "Insufficient signal" deviation. |
| URS-007 | REST API collection-endpoint N+1 query patterns SHALL be eliminated. | `benchmarks/results/rest-controller-audit.csv` categorization + `_prime_*`/`update_*_cache` audit in each controller. | ≥ 50 % query reduction per fixed endpoint | Controller-by-controller query-count analysis captured in OQ/PQ. |
| URS-008 | All existing automated tests SHALL continue to pass, with zero new regressions. | PHPUnit `vendor/bin/phpunit --testsuite default` + `restapi-autosave` suite + QUnit `grunt qunit`. | Zero new failures/errors beyond documented pre-existing baseline | OQ records test counts and delta vs. baseline. |
| URS-009 | Public PHP method signatures and hook contracts SHALL remain unchanged. | Static signature diff on `WP_Query`, `WP_Hook`, `wpdb`, `WP_REST_Server`, `WP_REST_Request`, `WP_REST_Response`; hook-name scan. | No breaking API change | OQ records diff summary with binary pass/fail. |
| URS-010 | Observability instrumentation SHALL be shipped with the implementation. | Inspection of `tests/performance/wp-content/mu-plugins/server-timing.php` for `wp-bootstrap`, `wp-plugins`, `wp-files-loaded`, `wp-cache-hits`, `wp-cache-misses` headers. | ≥ 5 new Server-Timing metrics present | IQ confirms file presence and header emission. |
| URS-011 | Every implemented optimization SHALL be supported by a recorded decision in the Decision Log. | `../decision-log-and-traceability.md` section 1. | All optimization areas have ≥ 1 decision entry | OQ cross-references Decision Log coverage. |
| URS-012 | Every measured metric SHALL be traceable to at least one contributing decision, and every decision SHALL be traceable to at least one metric. | `../decision-log-and-traceability.md` sections 2–4 and `RTM-bidirectional.md`. | Zero orphan decisions, zero orphan metrics | OQ inspects both RTMs. |
| URS-013 | Every metric SHALL carry an ICH Q9 confidence classification (High/Medium/Low) with rationale proportional to qualification rigor. | `ICH-Q9-risk-classification.md`. | 100 % of metrics classified with rationale | PQ references risk classification for every reported metric. |
| URS-014 | Any metric that cannot be derived SHALL be rendered as "Insufficient signal" with an RTM Deviation Ref entry (Critical/Major/Minor impact, root cause, cascading impact, disposition). | `deviation-register.md`. | No silent drop; every gap logged | PQ cross-references deviation register where applicable. |
| URS-015 | All deliverables SHALL satisfy ALCOA+ principles. | `ALCOA-plus-compliance.md` with per-principle evidence. | 9/9 principles satisfied | QA approval on `signoff-record.md`. |
| URS-016 | All GAMP 5 Category 5 validation gates SHALL record a binary pass or fail prior to sign-off. | `GAMP5-category5-gates.md`. | All gates marked PASS (or blocker documented) | Gate table shows 100 % PASS. |

---

## 4. Exclusions (Non-Requirements)

The following are **explicitly out of scope** per AAP §0.3.2 / §0.8.5, and cannot be the basis of a URS:

- jQuery / Backbone.js removal
- ES module migration or TypeScript conversion
- Server / nginx / Apache / PHP-FPM tuning
- CDN or edge caching implementation
- Database engine changes
- Bundled theme or Gutenberg source modification
- Any change to public API surface (method signatures, hook names, REST schemas, `wp.*` globals, enqueue dependency system behavior)
- Visual or user-facing functional change to the admin UI

Any regression that results from touching an excluded area is a **Critical deviation** requiring rollback.

---

## 5. Freeze Control

This URS is considered frozen at the timestamp recorded in the document header. Any change after that timestamp requires:

1. A new version stamped here.
2. A change-control entry in `signoff-record.md`.
3. Re-execution of the dependent PQ activities.

**Freeze Attestation:** All URSs in §3 are authored, reviewed for self-consistency, and frozen for PQ execution.
