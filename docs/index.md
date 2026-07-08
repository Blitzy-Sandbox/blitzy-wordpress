# blitzy-wordpress

WordPress 7.0 cross-cutting performance optimization — PHP runtime, database, cache, JS delivery, and observability

This project is a measurement-driven, evidence-first performance refactor of the WordPress 7.0 core runtime, where every change follows a strict **profile → quantify → fix → prove → document** mandate — no speculative optimization. The work spans the PHP runtime hot path (bootstrap and hook dispatch), the database/query layer, the object cache, template-tag N+1 elimination, REST API serialization, and JavaScript/asset delivery, and it ships with a reproducible benchmark harness and test-only Server-Timing observability. Every optimization preserves public API signatures, hook names and argument counts, REST routes and schemas, and the `wp.*` JavaScript surface — and baseline parity of the full test suites (28,930 PHPUnit and 456 QUnit tests) is the acceptance criterion, with module suites covering every modified file verified and full-suite parity confirmed in final validation.

## Documentation

- [Project Guide](project-guide.md) — completion status, key accomplishments, test outcomes, the AAP deliverable inventory, and the developer/runbook guide.
- [Technical Specifications](technical-specifications.md) — the architecture, scope boundaries, file-by-file transformation mapping, and the before/after Mermaid diagrams of the runtime.

## Benchmark deliverables

The measurement evidence and rule-mandated artifacts live under the repository's `benchmarks/results/` directory (outside the MkDocs tree, so open them from a repository checkout rather than the docs site):

- `../benchmarks/results/decision-log-and-traceability.md` — the decision log (decision, alternatives, rationale, risks) plus the bidirectional requirements-to-implementation traceability matrix (Rule 2 — Explainability).
- `../benchmarks/results/performance-dashboard.md` — the dashboard template visualizing the seven Server-Timing metrics against the six KPI targets (Rule 1 — Observability).
- `../benchmarks/results/executive-presentation.html` — the single, self-contained reveal.js executive deck for non-technical leadership (Rule 4 — Executive Presentation).
- `../benchmarks/results/benchmark-report.json` — the machine-readable aggregate of the before/after measurements (F-011).

The before/after runs are produced by the benchmark harness in `benchmarks/` (`run-baseline.sh`, `run-optimized.sh`, `run-benchmark.js`, `generate-diff-report.js`) against the isolated, pinned environment defined in `docker-compose.benchmark.yml`.

## Performance targets

| # | Target | Threshold | Measurement instrument | Status |
|---|--------|-----------|------------------------|--------|
| 1 | Front-end TTFB (uncached) | ≥ 20% reduction | Playwright performance suite | ❌ Not met — real improvement (−10.06%, 390.60 → 351.30 ms) |
| 2 | Admin DOMContentLoaded | ≥ 15% reduction | Playwright performance suite | ❌ Not met — flat (−0.17%, 1026.75 → 1028.50 ms) |
| 3 | Admin JS transfer size (gzipped) | ≥ 30% reduction | Runtime browser-measured gzipped transfer (`PerformanceResourceTiming.transferSize`) | ❌ Not met — flat (−0.00%, 11296.12 → 11296.68 KB) |
| 4 | PHP memory per front-end request | ≥ 10% reduction | `memory_get_peak_usage()` | ❌ Not met — real improvement (−5.31%, 6.77 → 6.41 MB) |
| 5 | DB queries per front-end page | ≥ 15% reduction | `SAVEQUERIES` count | ❌ Not met — flat (0.00%, 21 → 21) |
| 6 | PHP files loaded per front-end request | ≥ 30% reduction | `get_included_files()` count | ❌ Not met — real improvement (−12.58%, 493 → 431) |

**Zero of the six aggressive targets are met**, per the reproducible, genuinely-measured report (`../benchmarks/results/benchmark-report.json`, 10 iterations × 2 repetitions). Three metrics show real, statistically-significant movement in the right direction below target — PHP files loaded −12.58% (62 fewer files/request), front-end TTFB −10.06%, and PHP memory −5.31% — while three are flat: admin DOMContentLoaded and admin JS transfer (the admin bundles load eagerly by design and webpack cannot split the Grunt-uglified admin JS) and DB queries (already at the batched WordPress 6.1+ floor). Every improvement is bounded by a strict byte-identical-output guarantee and full test-suite parity (28,930 PHPUnit + 456 QUnit). The gaps to target are documented as accepted partials with AAP citations in the decision log (DEV-02, DEV-11, DEV-12, DEV-13). See the [Project Guide](project-guide.md) for the full breakdown and remaining work.
