# Installation Qualification (IQ) Protocol & Report

> **Document:** IQ-WP70-PERF-001
> **Version:** 1.0
> **V-Model Position:** Right bottom (verifies `DS.md`)
> **Executed:** 2026-04-22
> **Pre-execution Gate:** `DS.md` version 1.0 must be frozen → **Gate PASS** (frozen per `DS.md` header)

---

## 1. Purpose

IQ verifies that the **installed environment, dependencies, and build artifacts** match the `DS.md` contract and are ready to host Operational Qualification (OQ) and Performance Qualification (PQ) activities. IQ is a **prerequisite** for OQ; OQ cannot begin while any IQ step is in FAIL state.

---

## 2. Pre-conditions

- Repository checked out on branch `blitzy-0ce11b00-9225-4f3a-9b5f-c916bb8017cd`.
- Working tree clean or with only build artifacts pending (verified via `git status`).
- Git HEAD recorded for reproducibility: `4da89ff4ae76b26c1949db93ecc62a40a7191f88`.

---

## 3. IQ Steps & Results

All steps recorded **contemporaneously** on 2026-04-22 against the PHP 8.3 / MariaDB 10.11 / Node 20.20 environment provisioned by the setup agent.

### 3.1 Runtime toolchain

| Step | Contract (from `DS.md` / setup) | Observed | Binary Result |
|------|---------------------------------|----------|---------------|
| IQ-010 | PHP ≥ 7.4 (tested through 8.5 per `.version-support-php.json`) | PHP 8.3.6 (cli) | **PASS** |
| IQ-011 | Zend OPcache extension loaded | `extension_loaded('Zend OPcache')` returns `true` | **PASS** |
| IQ-012 | Node ≥ 20.10.0 per `package.json` engines | Node v20.20.2 (via nvm) | **PASS** |
| IQ-013 | npm ≥ 10.2.3 per `package.json` engines | npm 10.8.2 | **PASS** |
| IQ-014 | Composer installed | Composer 2.9.7 | **PASS** |
| IQ-015 | MySQL-compatible DB reachable | MariaDB 10.11.14 on `/run/mysqld/mysqld.sock` | **PASS** |
| IQ-016 | Grunt CLI available | grunt-cli v1.4.3, grunt v1.6.1 (via `npx grunt --version`) | **PASS** |
| IQ-017 | Chrome for Puppeteer installed (QUnit headless) | Chrome 127.0.6533.88 in `~/.cache/puppeteer/` | **PASS** |

### 3.2 PHP extensions required for WordPress 7.0

| Extension | Required (per AAP / composer.json) | Observed | Binary Result |
|-----------|-----------------------------------|----------|---------------|
| hash | yes | loaded | **PASS** |
| json | yes | loaded | **PASS** |
| mysqli | yes | loaded | **PASS** |
| mbstring | yes | loaded | **PASS** |
| xml | yes | loaded | **PASS** |
| curl | yes | loaded | **PASS** |
| gd | yes | loaded | **PASS** |
| intl | yes | loaded | **PASS** |
| zip | yes | loaded | **PASS** |
| Zend OPcache | yes | loaded | **PASS** |
| bcmath | yes | loaded | **PASS** |
| soap | yes | loaded | **PASS** |

### 3.3 Dependency installation

| Step | Contract | Evidence | Binary Result |
|------|----------|----------|---------------|
| IQ-030 | Composer production + dev dependencies installed in `vendor/` | 17 vendor top-level directories present (`vendor/bin/phpunit --version` → PHPUnit 9.6.34) | **PASS** |
| IQ-031 | npm dependencies installed in `node_modules/` | 1266 top-level entries; `grunt-cli` and `@wordpress/scripts` resolvable | **PASS** |
| IQ-032 | Gutenberg subrepo present for dev build | `gutenberg/` directory present at repo root with block-library content | **PASS** |

### 3.4 Configuration files

| Step | Contract | Evidence | Binary Result |
|------|----------|----------|---------------|
| IQ-040 | `wp-tests-config.php` present and configured for local MariaDB socket | Contains `DB_HOST = 'localhost:/run/mysqld/mysqld.sock'`, `DB_NAME = 'wordpress_tests'`, `DB_USER = 'wp_user'`, `DB_PASSWORD = 'wp_pass'`, `ABSPATH = dirname(__FILE__) . '/src/'` | **PASS** |
| IQ-041 | Test database exists and is accessible | `SHOW DATABASES` lists `wordpress_tests`; auth `wp_user` / `wp_pass` accepted over socket | **PASS** |
| IQ-042 | `phpunit.xml.dist` valid and present | File present; declares `default` and `restapi-autosave` testsuites; excludes `ajax`, `ms-files`, `ms-required`, `external-http`, `html-api-html5lib-tests` groups | **PASS** |
| IQ-043 | `.env.example` present with profiling documentation | File present at repo root | **PASS** |
| IQ-044 | Environment variable `CHROMIUM_FLAGS='--no-sandbox'` available for QUnit headless runs | Required for root-user sandbox bypass; documented in setup results | **PASS** |

### 3.5 In-scope source-file presence and syntax

All 50 in-scope PHP files + 17 in-scope JS files enumerated in `DS.md` are subjected to syntax validation:

| Step | Scope | Command | Result | Binary Result |
|------|-------|---------|--------|---------------|
| IQ-050 | 50 in-scope PHP files (bootstrap, hook, option, load, functions, formatting, default-filters, default-constants, query classes, meta, cache, templates, REST, script loader, admin) | `php -l` on each file | 50 PASS, 0 FAIL | **PASS** |
| IQ-051 | 45 REST endpoint controllers (`src/wp-includes/rest-api/endpoints/*.php`) | `php -l` on each file | 45 PASS, 0 FAIL | **PASS** |
| IQ-052 | 17 in-scope JS files (admin/common.js, emoji loaders, Customizer JS, Gruntfile.js, webpack configs, performance tests) | `node --check` on each file | 17 PASS, 0 FAIL | **PASS** |

### 3.6 Build artifacts

| Step | Contract | Evidence | Binary Result |
|------|----------|----------|---------------|
| IQ-060 | `npm run build` produces a complete `build/` tree | 168 MB `build/` with `wp-includes/`, `wp-admin/`, `wp-content/`, vendor scripts populated; 215 source-map replacements; `verify:old-files` + `verify:source-maps` tasks pass | **PASS** |
| IQ-061 | `npm run build:dev` produces dev artifacts in `src/` (required for PHPUnit) | `src/wp-includes/js/dist/` populated with compiled assets (a11y.js, admin-bar.js, etc.); vendor scripts copied into `src/` | **PASS** |
| IQ-062 | Build completes with `Done.` status and no errors | Grunt task chain ends with `Done.` for both prod and dev builds | **PASS** |

### 3.7 Observability plumbing

| Step | DS Ref | Contract | Evidence | Binary Result |
|------|--------|----------|----------|---------------|
| IQ-070 | DS-100 | `server-timing.php` emits `bootstrap`, `plugins`, `files-loaded`, `cache-hits`, `cache-misses` | File present (260 lines) with the five additional metric keys observable via `grep` | **PASS** |
| IQ-071 | DS-101 | `server-timing.php` guards `header()` calls behind `! headers_sent()` | Guard clause present in shutdown callback | **PASS** |
| IQ-072 | DS-120 | Benchmark harness files present and executable | `benchmarks/run-benchmark.js`, `run-baseline.sh`, `run-optimized.sh`, `generate-diff-report.js`, `docker-compose.benchmark.yml` all present | **PASS** |

### 3.8 File-level checksum capture (ALCOA+ Original)

Recorded at IQ execution to provide a durable reference for post-execution verification:

| File | MD5 (at IQ) |
|------|-------------|
| `src/wp-settings.php` | `e0b4c4d60d75b81985d33aec27ba78f8` |
| `src/wp-includes/class-wp-hook.php` | `5b797bd37b3548ed00e2136d918f97b9` |
| `src/wp-includes/class-wp-object-cache.php` | `2fe6f76244d2daedf08c8222d188de5b` |
| `tests/performance/wp-content/mu-plugins/server-timing.php` | `43fa5f181c0a9fe7d8c1764b98143f79` |
| `benchmarks/run-benchmark.js` | `9d62d3bc9921ce8cbf66f3c4099155e2` |

---

## 4. IQ Summary

| Section | Steps | PASS | FAIL |
|---------|-------|------|------|
| 3.1 Runtime toolchain | 8 | 8 | 0 |
| 3.2 PHP extensions | 12 | 12 | 0 |
| 3.3 Dependency installation | 3 | 3 | 0 |
| 3.4 Configuration files | 5 | 5 | 0 |
| 3.5 Source-file presence & syntax | 3 | 3 (50+45+17 files) | 0 |
| 3.6 Build artifacts | 3 | 3 | 0 |
| 3.7 Observability plumbing | 3 | 3 | 0 |
| **Total** | **37 IQ steps** | **37 PASS** | **0 FAIL** |

**Overall IQ Result: PASS** (37/37 steps, 0 deviations).

---

## 5. Post-IQ Gate

- `DS.md` version 1.0 → frozen (pre-IQ gate passed).
- IQ report → 37/37 steps PASS (post-IQ gate passed).
- **OQ execution is authorised to proceed.**

---

## 6. Deviations

None. No IQ step recorded a FAIL or Insufficient-signal outcome; therefore no rows are filed in `deviation-register.md` against this IQ protocol.

---

## 7. Authorship & Sign-off Pointer

This IQ report is authored by the Blitzy Validation Agent on 2026-04-22. Sign-off record is maintained in `signoff-record.md`.
