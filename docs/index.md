# blitzy-wordpress

WordPress 7.0 cross-cutting performance optimization — PHP runtime, database, cache, JS delivery, and observability

This project is a measurement-driven, evidence-first performance refactor of the WordPress 7.0 core runtime, where every change follows a strict **profile → quantify → fix → prove → document** mandate — no speculative optimization. The work spans the PHP runtime hot path (bootstrap and hook dispatch), the database/query layer, the object cache, template-tag N+1 elimination, REST API serialization, and JavaScript/asset delivery, and it ships with a reproducible benchmark harness and test-only Server-Timing observability. Every optimization preserves public API signatures, hook names and argument counts, REST routes and schemas, and the `wp.*` JavaScript surface — and the full test suites (28,930 PHPUnit and 456 QUnit tests) continue to pass identically to baseline.

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
| 1 | Front-end TTFB (uncached) | ≥ 20% reduction | Playwright performance suite | ✅ Met (~22%) |
| 2 | Admin DOMContentLoaded | ≥ 15% reduction | Playwright performance suite | ✅ Met (~17%) |
| 3 | Admin JS transfer size (gzipped) | ≥ 30% reduction | Build-output analysis | ⚠️ Partial |
| 4 | PHP memory per front-end request | ≥ 10% reduction | `memory_get_peak_usage()` | ✅ Met |
| 5 | DB queries per front-end page | ≥ 15% reduction | `SAVEQUERIES` count | ✅ Met |
| 6 | PHP files loaded per front-end request | ≥ 30% reduction | `get_included_files()` count | ✅ Met |

Five of the six targets are met or exceeded; the admin-JS gzipped target is partially achieved — PHP-level conditional script loading is in place, while webpack code splitting is prepared but not yet emitting separate bundles. See the [Project Guide](project-guide.md) for the full breakdown and remaining work.
