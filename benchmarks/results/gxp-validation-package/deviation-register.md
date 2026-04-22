# Deviation Register

> **Document:** DEV-REG-WP70-PERF-001
> **Version:** 1.0
> **Scope:** All deviations, residual risks, and "Insufficient signal" events for the WordPress 7.0 core performance qualification package
> **Date:** 2026-04-22

---

## 1. Purpose

This register is the **single authoritative source** for every deviation observed during IQ / OQ / PQ execution. It satisfies the user's directive that metrics which cannot be derived MUST NOT be silently dropped — they MUST be rendered as "Insufficient signal [specific reason]" with a corresponding RTM Deviation Ref entry that includes impact classification, root cause, cascading impact, and disposition.

---

## 2. Deviation Entries

### DEV-001 — Pre-existing PHP 8.3 Timezone Deprecation Failures

| Field | Value |
|-------|-------|
| **Deviation ID** | DEV-001 |
| **Opened** | 2026-04-22 |
| **Linked URS** | URS-008 (Test-suite regression; inherited baseline) |
| **Observed at** | OQ-101 — full PHPUnit default suite execution |
| **Description** | The full PHPUnit default suite reports **3 errors + 4 failures** attributable to PHP 8.3's stricter `DateInvalidTimeZoneException` behavior when legacy timezone identifiers (e.g. `America/Buenos_Aires`, `Canada/Newfoundland`) are passed. Failing tests: `Tests_Date_CurrentTime::test_should_work_with_deprecated_timezone`, `Tests_Date_CurrentTime::test_partial_hour_timezones_match_datetime_offset`, `Tests_Date_mysql2date::test_mysql2date_should_format_time_with_deprecated_time_zone`, `Tests_Admin_IncludesSchema::test_populate_options_when_locale_uses_deprecated_timezone_string`, `Tests_Date_DateI18n::test_adjusts_format_based_on_deprecated_timezone_string`, `Tests_Date_wpTimezone::test_should_return_deprecated_timezone_string`, `Tests_Option_SanitizeOption::test_sanitize_option`. |
| **Impact Classification** | **Minor** |
| **Root Cause** | Tests reference deprecated PHP timezone strings not accepted by PHP 8.3+. Failure exists on the baseline commit prior to any performance work; it is not caused by changes on branch `blitzy-0ce11b00-9225-4f3a-9b5f-c916bb8017cd`. |
| **Cascading Impact** | None on URS-001..URS-007. The 7 failing tests do not exercise bootstrap, hook, query, object cache, template, REST, script loader, nor observability code paths. Zero-overlap confirmed by grep of test file `use` and `@covers` annotations versus AAP in-scope file list. |
| **Disposition** | **Accepted.** The failing test fixtures and the timezone-parsing source code they reference are **outside the AAP in-scope list** (`tests/phpunit/tests/date/*.php`, `tests/phpunit/tests/option/sanitizeOption.php`, `tests/phpunit/tests/admin/includesSchema.php` and their subject source files). Per the user's refine-PR instructions and AAP §0.3.2, they are explicitly out of scope. Setup agent documented this baseline in the setup log. |
| **Evidence** | `OQ-protocol.md` §3.1; Setup agent log (pre-session); `../verification-suite-report.md` §Pre-existing failures. |
| **Owner** | Blitzy Validation Agent |
| **Status** | Closed — Accepted |

---

### DEV-002 — URS-003: Admin JS Transfer Size Numeric Delta

| Field | Value |
|-------|-------|
| **Deviation ID** | DEV-002 |
| **Opened** | 2026-04-22 |
| **Linked URS** | URS-003 (Admin JS transfer size ≥ 30 % reduction) |
| **Observed at** | PQ-003 — Performance Qualification step for URS-003 |
| **Description** | A numeric % reduction for the gzipped admin JS transfer size per admin page was **not captured** in this PQ cycle. A paired end-to-end Playwright navigation against both baseline and optimized Docker stacks, with Chrome DevTools Protocol `Network.responseReceivedExtraInfo` capture (gzipped byte counts), is required and was not performed. |
| **Impact Classification** | **Major** |
| **Root Cause** | The `benchmarks/docker-compose.benchmark.yml` harness that pairs baseline and optimized runs was not stood up in this PQ cycle. `tests/performance/specs/admin.test.js` **does contain** the hook to read transfer-size metrics, and `tests/performance/utils.js` contains the formatter; however, the Playwright run that would populate both baseline and optimized JSON artifacts was not executed. |
| **Cascading Impact** | The specific URS-003 numeric claim is unavailable for this cycle. URS-001 (TTFB) and URS-002 (DCL) are High-confidence PASS and are sufficient for the headline user-visible performance story; however, URS-003 as a standalone commitment is not numerically demonstrated. |
| **Disposition** | **Mitigated + Unresolved.** **Mitigation:** Instrumentation is shipped and reviewable (see commits `b9aa0e8c04` webpack code splitting, `ae9a171d75` media webpack optimization, and script-loader.php conditional-registration changes in `DS-083`, plus `tests/performance/specs/admin.test.js` transfer-size hooks and `tests/performance/utils.js` formatter). Any future PQ cycle can compute the numeric value by running the existing harness against both stacks. **Unresolved:** Numeric proof for URS-003 is not recorded in this package. `PQ-protocol.md` PQ-003 and `ICH-Q9-risk-classification.md` §3.7 render this as **Insufficient signal** and classify it as **Low**, never as a High-confidence claim. |
| **Evidence** | `PQ-protocol.md` §3 PQ-003; `ICH-Q9-risk-classification.md` §3.7; `RTM-bidirectional.md` §5. |
| **Owner** | Blitzy Validation Agent |
| **Status** | Open — Mitigated (instrumentation) + Unresolved (numeric) |

---

### DEV-003 — URS-004: PHP Memory per Front-End Request Numeric Delta

| Field | Value |
|-------|-------|
| **Deviation ID** | DEV-003 |
| **Opened** | 2026-04-22 |
| **Linked URS** | URS-004 (PHP memory per front-end request ≥ 10 % reduction) |
| **Observed at** | PQ-004 |
| **Description** | A numeric % reduction for `memory_get_peak_usage()` per front-end request was **not captured** in this PQ cycle. Baseline/optimized paired sampling under matched request conditions is required and was not performed. |
| **Impact Classification** | **Major** |
| **Root Cause** | Paired baseline/optimized Docker stack run not executed in this PQ cycle. Instrumentation exists: `tests/performance/wp-content/mu-plugins/server-timing.php` ships the `memory-usage` Server-Timing key, and `tests/performance/specs/home.test.js` reads it, so the measurement path is active and verifiable. Missing: paired capture. |
| **Cascading Impact** | URS-004 numeric claim unavailable. Design-level evidence (deferred bootstrap in `wp-settings.php`, reduced hook dispatch allocations in `class-wp-hook.php`, fewer copy-on-write expansions) supports directional intent but cannot be promoted above Low without measurement. |
| **Disposition** | **Mitigated + Unresolved.** **Mitigation:** Full instrumentation path shipped; commits `eaa1298256` (server-timing extension) and `08c0d563ac` (WP_Object_Cache counters) provide memory observability; any future cycle can derive the number by running the benchmark harness. **Unresolved:** Numeric proof for URS-004 is not recorded. `PQ-protocol.md` PQ-004 and `ICH-Q9-risk-classification.md` §3.7 render this as **Insufficient signal** / **Low**. |
| **Evidence** | `PQ-protocol.md` §3 PQ-004; `ICH-Q9-risk-classification.md` §3.7; `RTM-bidirectional.md` §5. |
| **Owner** | Blitzy Validation Agent |
| **Status** | Open — Mitigated (instrumentation) + Unresolved (numeric) |

---

### DEV-004 — URS-005: Front-End Whole-Page DB Queries Numeric Delta

| Field | Value |
|-------|-------|
| **Deviation ID** | DEV-004 |
| **Opened** | 2026-04-22 |
| **Linked URS** | URS-005 (Front-end whole-page DB queries ≥ 15 % reduction) |
| **Observed at** | PQ-005 (whole-page portion). PQ-007 (REST per-endpoint portion) is **separately PASS** and not covered by this deviation. |
| **Description** | A numeric % reduction in `SAVEQUERIES` query count for a typical front-end whole-page render, comparing baseline vs. optimized, was **not captured** in this PQ cycle for the whole-page case. The REST API per-endpoint portion of URS-005 is fully measured and documented (see PQ-007, ≥ 90 % per fixed endpoint). |
| **Impact Classification** | **Major** (for the whole-page numeric); unaffected PQ-007 per-endpoint portion remains High-confidence PASS. |
| **Root Cause** | Paired baseline/optimized whole-page capture under `SAVEQUERIES=true` wp-config was not executed in this PQ cycle. Instrumentation is in place: `tests/performance/wp-content/mu-plugins/server-timing.php` emits `db-queries` Server-Timing header, and `WP_Query`, `WP_Meta_Query`, `wpdb` optimizations (commits `b9ae74a027`, `06ded4e5fb`, `6168c55dfa`) are in force. |
| **Cascading Impact** | URS-005 whole-page numeric claim unavailable; however, URS-007 (N+1 elimination in REST per-endpoint) carries a High-confidence PASS with ≥ 90 % reduction per fixed endpoint, which is a strict superset of the URS-005 intent for those request types. The whole-page HTML-front-end path remains instrumented but unquantified for this cycle. |
| **Disposition** | **Mitigated + Unresolved.** **Mitigation:** REST per-endpoint portion is High-confidence PASS; whole-page instrumentation shipped. **Unresolved:** Whole-page HTML-render numeric proof for URS-005 is not recorded. `PQ-protocol.md` PQ-005 splits the URS into a PASS component (REST) and an Insufficient-signal component (whole-page). |
| **Evidence** | `PQ-protocol.md` §3 PQ-005 + PQ-007; `ICH-Q9-risk-classification.md` §3.7; `RTM-bidirectional.md` §5. |
| **Owner** | Blitzy Validation Agent |
| **Status** | Open — Mitigated (instrumentation + REST PASS) + Unresolved (whole-page numeric) |

---

### DEV-005 — URS-006: Files-Loaded Numeric Delta

| Field | Value |
|-------|-------|
| **Deviation ID** | DEV-005 |
| **Opened** | 2026-04-22 |
| **Linked URS** | URS-006 (Files loaded per front-end request ≥ 30 % reduction) |
| **Observed at** | PQ-006 |
| **Description** | A numeric % reduction for `count(get_included_files())` per front-end request, comparing baseline vs. optimized, was **not captured** in this PQ cycle. |
| **Impact Classification** | **Major** (for the numeric proof). |
| **Root Cause** | Paired baseline/optimized capture not executed in this PQ cycle. Instrumentation is in place: `tests/performance/wp-content/mu-plugins/server-timing.php` emits `files-loaded` Server-Timing key, and commit `dee13d5c13` (`fix: achieve ≥30% PHP files loaded reduction`) plus `849c1293bd` (context-aware deferred loading in `wp-settings.php`) establish the design-level pathway to the target. |
| **Cascading Impact** | URS-006 numeric claim unavailable. Strong static design evidence: the bootstrap contained 306 unconditional requires; deferred loading blocks have been introduced for block editor, REST controllers, AI / Collaboration / Abilities / Connectors subsystems and more. Without runtime capture the specific %-improvement achieved is unconfirmed. |
| **Disposition** | **Mitigated + Unresolved.** **Mitigation:** Design evidence is strong (source of deferred-loading pattern is reviewable in the commits above); instrumentation is shipped and active. **Unresolved:** Numeric proof is not recorded. `PQ-protocol.md` PQ-006 and `ICH-Q9-risk-classification.md` §3.7 render this as **Insufficient signal** / **Low**. |
| **Evidence** | `PQ-protocol.md` §3 PQ-006; `ICH-Q9-risk-classification.md` §3.7; `RTM-bidirectional.md` §5; commits `849c1293bd` + `dee13d5c13`. |
| **Owner** | Blitzy Validation Agent |
| **Status** | Open — Mitigated (design evidence + instrumentation) + Unresolved (numeric) |

---

## 3. Register Summary

| Deviation | Impact | Disposition | Status |
|-----------|--------|-------------|--------|
| DEV-001 | Minor | Accepted | Closed |
| DEV-002 | Major | Mitigated + Unresolved | Open |
| DEV-003 | Major | Mitigated + Unresolved | Open |
| DEV-004 | Major | Mitigated + Unresolved | Open |
| DEV-005 | Major | Mitigated + Unresolved | Open |

- **Critical deviations:** 0 (none blocking release).
- **Major deviations:** 4 (all with Mitigated component and all unresolved numeric portions are instrumentation-ready for future cycle).
- **Minor deviations:** 1 (Accepted; out-of-scope test fixtures).

**None of these deviations invalidate the High-confidence PASS results for URS-001 (TTFB), URS-002 (DCL), URS-007 (REST N+1), URS-008 (zero regressions), URS-009 (API preservation), URS-010 (observability), URS-011..URS-012 (traceability), URS-013..URS-016 (meta-compliance).**

---

## 4. Residual Risk Statement

The four Major deviations (DEV-002..DEV-005) share a single root cause: this PQ cycle did not stand up a paired baseline/optimized Docker benchmark run for non-TTFB / non-DCL metrics. The corrective action for any future cycle is mechanical: invoke `benchmarks/run-baseline.sh` followed by `benchmarks/run-optimized.sh` with the relevant Server-Timing keys enabled, then run `benchmarks/generate-diff-report.js` to produce the numeric deltas. All files and scripts required for that invocation are present and unchanged.

Because the High-confidence PASS set (URS-001, URS-002, URS-007) covers the user-visible performance story, and because all four Major deviations are transparently flagged and Low-classified, release is permitted under GAMP 5 §7.3 (Residual Risk) — see `GAMP5-category5-gates.md` Gate G-07 and §4.

---

## 5. Cross-References

- `PQ-protocol.md` — each Insufficient-signal PQ step cites its DEV-xxx entry.
- `ICH-Q9-risk-classification.md` §3.7 — each Low metric cites its DEV-xxx entry.
- `RTM-bidirectional.md` §5 — RTM Deviation Refs table cross-indexes deviations to URS.
- `GAMP5-category5-gates.md` Gate G-07, G-09 — gates that accept these deviations.
- `signoff-record.md` — signed-off acceptance of the residual risk.
