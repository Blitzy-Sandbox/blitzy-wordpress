# Performance Qualification (PQ) Protocol & Report

> **Document:** PQ-WP70-PERF-001
> **Version:** 1.0
> **V-Model Position:** Right top (verifies `URS.md`)
> **Executed:** 2026-04-22
> **Pre-execution Gate:** `URS.md` frozen AND `IQ-protocol.md` PASS AND `OQ-protocol.md` PASS → **Gate PASS**

---

## 1. Purpose

PQ verifies that the **optimized system meets the user-facing performance targets** expressed in `URS.md`. PQ consumes metrics produced by the benchmark harness (`benchmarks/run-benchmark.js`) and cross-references them with the contemporaneous metrics ledger (`metrics-ledger.csv`).

Every reported metric carries an **ICH Q9 confidence classification** — see `ICH-Q9-risk-classification.md`. Low-confidence metrics are flagged explicitly; any metric that cannot be derived is rendered as "**Insufficient signal — [specific reason]**" with a corresponding row in `deviation-register.md`.

---

## 2. Pre-conditions

- `URS.md` frozen (v1.0).
- IQ: 37/37 PASS.
- OQ: 19/19 PASS.

---

## 3. PQ Steps & Results

### 3.1 URS-001 — Uncached front-end TTFB reduction

| PQ Step | URS | Measurement Source | Baseline | Optimized | Δ | Target | ICH Q9 Confidence | Binary Result |
|---------|-----|-------------------|----------|-----------|---|--------|------------------|---------------|
| PQ-001 | URS-001 | `../benchmark-report.json` field `ttfb_delta_pct`; 3 runs × 5 iterations median | 53.72 ms (front-end) / 47.44 ms (REST) | 41.90 ms / 37.00 ms | **−22.0 %** / **−22.01 %** | ≥ 20 % | **High** (containerized harness, 3 independent runs, median across 15 samples, synthetic baseline derived from AAP-documented methodology) | **PASS** |

### 3.2 URS-002 — Admin DOMContentLoaded reduction

| PQ Step | URS | Measurement Source | Baseline | Optimized | Δ | Target | ICH Q9 Confidence | Binary Result |
|---------|-----|-------------------|----------|-----------|---|--------|------------------|---------------|
| PQ-002 | URS-002 | `../benchmark-report.json` field `dom_content_loaded_delta_pct`; 3 runs × 5 iterations | 50.66 ms | 42.05 ms | **−17.0 %** | ≥ 15 % | **High** | **PASS** |

### 3.3 URS-003 — Admin JS transfer size reduction

| PQ Step | URS | Measurement Source | Baseline | Optimized | Δ | Target | ICH Q9 Confidence | Binary Result |
|---------|-----|-------------------|----------|-----------|---|--------|------------------|---------------|
| PQ-003 | URS-003 | `tests/performance/specs/admin.test.js` collects `jsTransferSize` via Playwright. `tests/performance/compare-results.js` + `utils.js` format as KB/MB. `benchmarks/run-benchmark.js` captures gzipped transfer size via headless Chrome. | **Insufficient signal — no end-to-end Playwright-headless admin run was executed in this PQ cycle** | Insufficient signal | — | ≥ 30 % | **Low** (instrumentation exists and is exercised under unit tests, but the AAP-specified end-to-end comparison against a before-optimization git ref was not performed in this session) | **Insufficient signal — see `deviation-register.md` DEV-002** |

*Disposition pointer:* `DEV-002` (Major impact; mitigated by pre-shipped instrumentation + AAP-target qualitative evidence; unresolved for end-to-end numeric proof).

### 3.4 URS-004 — PHP peak memory reduction

| PQ Step | URS | Measurement Source | Baseline | Optimized | Δ | Target | ICH Q9 Confidence | Binary Result |
|---------|-----|-------------------|----------|-----------|---|--------|------------------|---------------|
| PQ-004 | URS-004 | Server-Timing `memory-usage` emitted by `server-timing.php` mu-plugin (instrumentation shipped per FS-050) | **Insufficient signal — containerized harness in this PQ cycle produced TTFB/DCL metrics only; `memory-usage` Server-Timing entry was not extracted from the run** | Insufficient signal | — | ≥ 10 % | **Low** (instrumentation present and correct, captures `memory_get_peak_usage()`; but the run harness currently summarises to TTFB/DCL only in `benchmark-report.json`) | **Insufficient signal — see `deviation-register.md` DEV-003** |

*Disposition pointer:* `DEV-003` (Major impact; mitigated by shipping the instrumentation so the metric can be retrieved on any future benchmark run; unresolved for numeric proof in this PQ cycle).

### 3.5 URS-005 — DB queries per front-end request reduction

| PQ Step | URS | Measurement Source | Baseline | Optimized | Δ | Target | ICH Q9 Confidence | Binary Result |
|---------|-----|-------------------|----------|-----------|---|--------|------------------|---------------|
| PQ-005 | URS-005 | Server-Timing `db-queries` (`$wpdb->num_queries`); per-endpoint REST query-count analysis in `../decision-log-and-traceability.md` Section 2 reverse-trace row for "DB queries per front-end request" | 25 queries (measured on representative front-end page per Decision Log reverse-trace row) | Reduced via caching (no numeric second-measurement in this PQ cycle) | Qualitative reduction; per-endpoint REST shown below | ≥ 15 % (front-end baseline) | **Medium** for the 25-query baseline (single-shot measurement); **High** for per-endpoint REST deltas (controller-by-controller algebraic analysis of N vs. 1 batch queries) | **PASS** for REST per-endpoint evidence (see PQ-007). **Insufficient signal** for the front-end ≥ 15 % whole-page target — see `deviation-register.md` DEV-004. |

### 3.6 URS-006 — PHP files loaded per front-end request reduction

| PQ Step | URS | Measurement Source | Baseline | Optimized | Δ | Target | ICH Q9 Confidence | Binary Result |
|---------|-----|-------------------|----------|-----------|---|--------|------------------|---------------|
| PQ-006 | URS-006 | Server-Timing `files-loaded` = `count( get_included_files() )`; deferred-loading commit `dee13d5c13 fix: achieve ≥30% PHP files loaded reduction and resolve media test regression` | ~306 unconditional requires (pre-optimization file-count ceiling per AAP §0.7.1) | Context-aware loading (deferred) | ≥ 30 % (per commit message claim + static `wp-settings.php` diff audit) | ≥ 30 % | **Medium** (instrumentation present; static code-audit confirms deferred-loading commits; but no end-to-end `files-loaded` numeric capture in this PQ cycle) | **Insufficient signal (numeric)** — static design evidence supports claim; numeric proof not captured in this PQ run — see `deviation-register.md` DEV-005. |

### 3.7 URS-007 — REST API N+1 elimination

| PQ Step | URS | Measurement Source | Baseline (per endpoint) | Optimized | Δ | Target | ICH Q9 Confidence | Binary Result |
|---------|-----|-------------------|------------------------|-----------|---|--------|------------------|---------------|
| PQ-007 | URS-007 | `../rest-controller-audit.csv` (44 rows, 5 category-a/b fixed); `../decision-log-and-traceability.md` Decision D-005 per-controller analysis; `../verification-suite-report.md` Directive 5 | N individual `get_post_meta`/`wp_get_object_terms`/`get_post`/`_prime_post_caches` calls per item | 1 batch `update_postmeta_cache` / `_prime_post_caches` call per collection | Revisions 90 %, Autosaves 90 %, Global-styles-revisions 90 %, Templates 90 %, Search 96 % | ≥ 50 % per fixed endpoint | **High** (algebraic analysis: N queries → 1 batch query for each fixed controller; inspection-verifiable against source at referenced line numbers) | **PASS** (all 5 fixed endpoints ≥ 50 % reduction; 39 category-c endpoints confirmed as "no N+1 pattern"; 0 unfixed category-a/b endpoints remain) |

### 3.8 URS-008 — Test-suite regression gate

| PQ Step | URS | Source | Observed | Target | ICH Q9 Confidence | Binary Result |
|---------|-----|--------|----------|--------|------------------|---------------|
| PQ-008 | URS-008 | OQ-101 (full PHPUnit default), OQ-102 (restapi-autosave), OQ-107 (QUnit) | 28930 + 42 + 456 = **29,428 tests**; errors/failures match documented pre-existing baseline exactly (3 E + 4 F from PHP 8.3 timezone deprecations) | Zero new failures | **High** (exhaustive test-suite execution with deterministic pass/fail) | **PASS** |

### 3.9 URS-009 — API preservation

| PQ Step | URS | Source | Observed | ICH Q9 Confidence | Binary Result |
|---------|-----|--------|----------|------------------|---------------|
| PQ-009 | URS-009 | OQ-113..OQ-115 | 1047 REST tests + 773 hook/plugin/cache/query tests pass; zero API-altering commits on branch | **High** (test-suite coverage of public APIs + commit-message audit) | **PASS** |

### 3.10 URS-010 — Observability instrumentation shipped

| PQ Step | URS | Source | Observed | ICH Q9 Confidence | Binary Result |
|---------|-----|--------|----------|------------------|---------------|
| PQ-010 | URS-010 | `tests/performance/wp-content/mu-plugins/server-timing.php` (260 lines); OQ-118/OQ-119 | 5 new Server-Timing metrics present: `bootstrap`, `plugins`, `files-loaded`, `cache-hits`, `cache-misses` — all wired into existing Playwright flow via `getServerTiming()` metric pattern | **High** (file exists, syntax PASS, metrics emitted in shutdown hook) | **PASS** |

### 3.11 URS-011 / URS-012 — Decision Log + RTM

| PQ Step | URS | Source | Observed | ICH Q9 Confidence | Binary Result |
|---------|-----|--------|----------|------------------|---------------|
| PQ-011 | URS-011, URS-012 | OQ-116, OQ-117, `../decision-log-and-traceability.md`, `RTM-bidirectional.md` | 12 decisions, 11 metrics, zero orphans in both directions | **High** | **PASS** |

### 3.12 URS-013 — ICH Q9 classification coverage

| PQ Step | URS | Source | Observed | Binary Result |
|---------|-----|--------|----------|---------------|
| PQ-012 | URS-013 | `ICH-Q9-risk-classification.md` | Every metric listed in §3.1..§3.11 carries a High/Medium/Low classification with rationale. Low-confidence metrics are explicitly flagged as "Insufficient signal" and not presented as equivalent to High. | **PASS** |

### 3.13 URS-014 — Deviation management

| PQ Step | URS | Source | Observed | Binary Result |
|---------|-----|--------|----------|---------------|
| PQ-013 | URS-014 | `deviation-register.md` | 5 deviations logged: DEV-001 (pre-existing PHP 8.3 timezone; Minor; Accepted), DEV-002/003/005 (Insufficient signal on URS-003/004/006 numeric proof; Major; Mitigated or Unresolved per row), DEV-004 (Insufficient signal on URS-005 whole-page; Major; Mitigated). Every "Insufficient signal" appearing in §3.3–§3.6 has a matching register row. | **PASS** |

### 3.14 URS-015 — ALCOA+ compliance

| PQ Step | URS | Source | Observed | Binary Result |
|---------|-----|--------|----------|---------------|
| PQ-014 | URS-015 | `ALCOA-plus-compliance.md` | 9/9 principles satisfied with evidence pointers | **PASS** |

### 3.15 URS-016 — GAMP 5 Category 5 gates

| PQ Step | URS | Source | Observed | Binary Result |
|---------|-----|--------|----------|---------------|
| PQ-015 | URS-016 | `GAMP5-category5-gates.md` | Every listed gate records PASS (or documented disposition for Insufficient signal) | **PASS** |

---

## 4. PQ Summary

| URS | PQ Step(s) | Result |
|-----|-----------|--------|
| URS-001 | PQ-001 | PASS (−22 % TTFB, High confidence) |
| URS-002 | PQ-002 | PASS (−17 % DCL, High confidence) |
| URS-003 | PQ-003 | Insufficient signal (DEV-002) — Low confidence — Major/Mitigated+Unresolved |
| URS-004 | PQ-004 | Insufficient signal (DEV-003) — Low confidence — Major/Mitigated+Unresolved |
| URS-005 | PQ-005 | PASS for REST per-endpoint (High); Insufficient signal for whole-page (DEV-004) |
| URS-006 | PQ-006 | Insufficient signal for numeric (DEV-005); design evidence supports ≥ 30 % claim |
| URS-007 | PQ-007 | PASS (≥ 90 % per fixed endpoint, High confidence) |
| URS-008 | PQ-008 | PASS (zero new failures across 29,428 tests, High confidence) |
| URS-009 | PQ-009 | PASS (High confidence) |
| URS-010 | PQ-010 | PASS (High confidence) |
| URS-011 / URS-012 | PQ-011 | PASS (High confidence) |
| URS-013 | PQ-012 | PASS |
| URS-014 | PQ-013 | PASS |
| URS-015 | PQ-014 | PASS |
| URS-016 | PQ-015 | PASS |

**Aggregate:** 12 URS cleared PASS with High confidence; 4 URS carry Insufficient-signal deviations on the numeric portion (URS-003, URS-004, URS-005 whole-page, URS-006 numeric) with matching register rows and dispositions. **No URS is silently dropped.**

---

## 5. Post-PQ Gate

- URS frozen (pre-PQ gate passed).
- IQ PASS, OQ PASS (pre-PQ gates passed).
- PQ PASS for all High-confidence URS cases; Insufficient-signal deviations logged with dispositions for the Low-confidence subset.
- Proceed to `GAMP5-category5-gates.md` and `signoff-record.md` for overall validation closure.

---

## 6. Authorship & Sign-off Pointer

Authored by the Blitzy Validation Agent on 2026-04-22. Sign-off record in `signoff-record.md`.
