# Benchmark Diff Report

- Generated at: 2026-07-08T12:45:16.000Z
- Baseline: `before-performance-results.json` (present)
- Optimized: `performance-results.json`
- Repetitions: 2
- Iterations: 10

## Percentage conventions

All percentages in this report use the **baseline (`before`) value as the denominator**. Two column names appear, differing only in sign:

- **`Reduction %`** (KPI Targets table) = `(before - after) / before * 100`. **Positive = improvement** (the metric got smaller). A KPI passes when `Reduction %` >= its threshold.
- **`Diff %`** (Per-Suite Metrics tables) = `(after - before) / before * 100`. **Negative = improvement** (the metric got smaller); positive = regression.

The two columns are algebraic negations of each other over the same `before` denominator (`Reduction % = -Diff %`), so a single metric delta reads consistently across every table. Percentages are `null`/blank when no baseline is present or the baseline value is zero. Lower is better for every metric reported here.

## KPI Targets

| Target | Metric | Before | After | Reduction % | Threshold | Status | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| Front-end TTFB (uncached) | timeToFirstByte | 390.60 ms | 351.30 ms | 10.06 % | >= 20% | FAIL | 0.0000 | yes |
| Admin DOMContentLoaded | domContentLoaded | 1026.75 ms | 1028.50 ms | -0.17 % | >= 15% | FAIL | 0.6808 | no |
| Admin JS transfer size (gzipped) | adminJsTransferSize | 11296.12 KB | 11296.68 KB | -0.00 % | >= 30% | FAIL | 0.0000 | yes |
| PHP memory per front-end request | wpMemoryUsage | 6.77 MB | 6.41 MB | 5.31 % | >= 10% | FAIL | 0.0000 | yes |
| DB queries per front-end page | wpDbQueries | 21 | 21 | 0.00 % | >= 15% | FAIL | 1.0000 | no |
| PHP files loaded per front-end request | wpFilesLoaded | 493 | 431 | 12.58 % | >= 30% | FAIL | 0.0000 | yes |

Targets met: 0 / 6

## Per-Suite Metrics

### Admin › Locale: en_US (scope: admin)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 435.80 ms | 441.25 ms | 5.45 ms | 1.25 % | 10.94 ms | 3.00 ms | 0.4377 | no |
| domContentLoaded | 1033.80 ms | 1023.95 ms | -9.85 ms | -0.95 % | 24.14 ms | 7.80 ms | 0.4258 | no |
| adminJsTransferSize | 11296.12 KB | 11296.68 KB | 0.56 KB | 0.00 % | 0.00 KB | 0.00 KB | 0.0000 | yes |
| wpTotal | 424.58 ms | 429.50 ms | 4.92 ms | 1.16 % | 10.80 ms | 3.10 ms | 0.4957 | no |
| wpBootstrap | 318.13 ms | 321.85 ms | 3.71 ms | 1.17 % | 10.88 ms | 2.04 ms | 0.5995 | no |
| wpPlugins | 8.06 ms | 8.12 ms | 0.06 ms | 0.74 % | 0.13 ms | 0.06 ms | 0.4225 | no |
| wpFilesLoaded | 531 | 531 | 0 | 0.00 % | 0 | 0 | 1.0000 | no |
| wpCacheHits | 801 | 793 | -8 | -1.00 % | 2 | 2 | 0.0000 | yes |
| wpCacheMisses | 79 | 79 | 0 | 0.00 % | 0 | 0 | 1.0000 | no |
| wpMemoryUsage | 6.89 MB | 6.94 MB | 0.06 MB | 0.82 % | 0.00 MB | 0.00 MB | 0.0000 | yes |
| wpDbQueries | 39.5 | 39.5 | 0 | 0.00 % | 0.5 | 0.5 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Admin › Locale: de_DE (scope: admin)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 435.85 ms | 441.65 ms | 5.80 ms | 1.33 % | 9.80 ms | 4.20 ms | 0.3134 | no |
| domContentLoaded | 1020.20 ms | 1031.10 ms | 10.90 ms | 1.07 % | 22.63 ms | 18.45 ms | 0.1602 | no |
| adminJsTransferSize | 11296.12 KB | 11296.68 KB | 0.56 KB | 0.00 % | 0.00 KB | 0.00 KB | 0.0000 | yes |
| wpTotal | 424.54 ms | 429.60 ms | 5.06 ms | 1.19 % | 10.35 ms | 3.83 ms | 0.2779 | no |
| wpBootstrap | 317.95 ms | 321.44 ms | 3.49 ms | 1.10 % | 10.31 ms | 2.37 ms | 0.4615 | no |
| wpPlugins | 8.07 ms | 8.07 ms | 0.00 ms | 0.00 % | 0.12 ms | 0.07 ms | 0.8773 | no |
| wpFilesLoaded | 531 | 531 | 0 | 0.00 % | 0 | 0 | 1.0000 | no |
| wpCacheHits | 803 | 795 | -8 | -1.00 % | 1.7320508075688772 | 0 | 0.0000 | yes |
| wpCacheMisses | 79 | 79 | 0 | 0.00 % | 0 | 0 | 1.0000 | no |
| wpMemoryUsage | 6.89 MB | 6.94 MB | 0.06 MB | 0.83 % | 0.00 MB | 0.00 MB | 0.0000 | yes |
| wpDbQueries | 40 | 40 | 0 | 0.00 % | 0.4330127018922193 | 0 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentyone, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 383.25 ms | 346.25 ms | -37.00 ms | -9.65 % | 12.21 ms | 4.50 ms | 0.0000 | yes |
| largestContentfulPaint | 426.00 ms | 384.00 ms | -42.00 ms | -9.86 % | 13.06 ms | 4.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 42.10 ms | 38.35 ms | -3.75 ms | -8.91 % | 3.16 ms | 1.40 ms | 0.0840 | no |
| wpBeforeTemplate | 356.57 ms | 319.41 ms | -37.16 ms | -10.42 % | 11.71 ms | 3.72 ms | 0.0000 | yes |
| wpTemplate | 15.64 ms | 15.84 ms | 0.20 ms | 1.25 % | 1.56 ms | 0.40 ms | 0.8962 | no |
| wpTotal | 372.59 ms | 335.15 ms | -37.43 ms | -10.05 % | 12.02 ms | 4.22 ms | 0.0000 | yes |
| wpBootstrap | 317.51 ms | 280.79 ms | -36.72 ms | -11.56 % | 11.41 ms | 3.06 ms | 0.0000 | yes |
| wpPlugins | 7.96 ms | 7.86 ms | -0.11 ms | -1.32 % | 0.13 ms | 0.08 ms | 0.0699 | no |
| wpFilesLoaded | 501 | 439 | -62 | -12.38 % | 0 | 0 | 0.0000 | yes |
| wpCacheHits | 824 | 828 | 4 | 0.49 % | 2.1000000000000005 | 0 | 0.0000 | yes |
| wpCacheMisses | 51 | 51 | 0 | 0.00 % | 0.5999999999999998 | 0 | 1.0000 | no |
| wpMemoryUsage | 6.43 MB | 5.99 MB | -0.43 MB | -6.76 % | 0.00 MB | 0.00 MB | 0.0000 | yes |
| wpDbQueries | 20 | 20 | 0 | 0.00 % | 1.2000000000000002 | 0 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentyone, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 384.70 ms | 344.15 ms | -40.55 ms | -10.54 % | 103.73 ms | 3.30 ms | 0.4580 | no |
| largestContentfulPaint | 426.00 ms | 386.00 ms | -40.00 ms | -9.39 % | 103.47 ms | 6.00 ms | 0.4546 | no |
| lcpMinusTtfb | 40.60 ms | 41.30 ms | 0.70 ms | 1.72 % | 3.01 ms | 1.80 ms | 0.9180 | no |
| wpBeforeTemplate | 356.90 ms | 318.52 ms | -38.38 ms | -10.75 % | 74.45 ms | 4.29 ms | 0.0362 | yes |
| wpTemplate | 15.55 ms | 15.82 ms | 0.27 ms | 1.70 % | 1.18 ms | 0.28 ms | 0.9597 | no |
| wpTotal | 373.11 ms | 334.23 ms | -38.88 ms | -10.42 % | 74.44 ms | 4.28 ms | 0.0363 | yes |
| wpBootstrap | 318.29 ms | 280.01 ms | -38.28 ms | -12.03 % | 74.43 ms | 3.72 ms | 0.0354 | yes |
| wpPlugins | 7.98 ms | 7.91 ms | -0.08 ms | -0.94 % | 0.21 ms | 0.08 ms | 0.2254 | no |
| wpFilesLoaded | 501 | 439 | -62 | -12.38 % | 0 | 0 | 0.0000 | yes |
| wpCacheHits | 824 | 828 | 4 | 0.49 % | 1.525614630239236 | 0 | 0.0000 | yes |
| wpCacheMisses | 51 | 51 | 0 | 0.00 % | 0.4358898943540675 | 0 | 1.0000 | no |
| wpMemoryUsage | 6.43 MB | 5.99 MB | -0.43 MB | -6.76 % | 0.00 MB | 0.00 MB | 0.0000 | yes |
| wpDbQueries | 20 | 20 | 0 | 0.00 % | 0.8717797887081344 | 0 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentythree, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 384.10 ms | 347.30 ms | -36.80 ms | -9.58 % | 4.00 ms | 2.55 ms | 0.0000 | yes |
| largestContentfulPaint | 420.00 ms | 386.00 ms | -34.00 ms | -8.10 % | 4.92 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 34.85 ms | 37.00 ms | 2.15 ms | 6.17 % | 3.11 ms | 2.40 ms | 0.2968 | no |
| wpBeforeTemplate | 355.67 ms | 317.06 ms | -38.61 ms | -10.86 % | 61.32 ms | 2.96 ms | 0.0004 | yes |
| wpTemplate | 15.79 ms | 16.04 ms | 0.25 ms | 1.55 % | 2.78 ms | 0.64 ms | 0.6023 | no |
| wpTotal | 373.19 ms | 335.01 ms | -38.18 ms | -10.23 % | 61.08 ms | 3.79 ms | 0.0004 | yes |
| wpBootstrap | 318.21 ms | 279.56 ms | -38.65 ms | -12.15 % | 61.18 ms | 2.53 ms | 0.0004 | yes |
| wpPlugins | 7.97 ms | 7.92 ms | -0.06 ms | -0.69 % | 0.33 ms | 0.08 ms | 0.5508 | no |
| wpFilesLoaded | 501 | 439 | -62 | -12.38 % | 7.54247233265651 | 0 | 0.0000 | yes |
| wpCacheHits | 824 | 828 | 4 | 0.49 % | 97.30316884185567 | 0 | 0.8097 | no |
| wpCacheMisses | 51 | 51 | 0 | 0.00 % | 0.6531972647421813 | 0 | 1.0000 | no |
| wpMemoryUsage | 6.43 MB | 5.99 MB | -0.43 MB | -6.76 % | 0.20 MB | 0.00 MB | 0.0000 | yes |
| wpDbQueries | 20 | 20 | 0 | 0.00 % | 1.143095213298816 | 0 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentythree, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 385.25 ms | 345.80 ms | -39.45 ms | -10.24 % | 10.84 ms | 3.90 ms | 0.0000 | yes |
| largestContentfulPaint | 420.00 ms | 384.00 ms | -36.00 ms | -8.57 % | 11.76 ms | 4.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 35.10 ms | 35.80 ms | 0.70 ms | 1.99 % | 3.06 ms | 1.90 ms | 0.2478 | no |
| wpBeforeTemplate | 355.59 ms | 316.50 ms | -39.09 ms | -10.99 % | 53.53 ms | 2.97 ms | 0.0000 | yes |
| wpTemplate | 18.91 ms | 19.29 ms | 0.37 ms | 1.96 % | 2.61 ms | 1.98 ms | 0.4491 | no |
| wpTotal | 374.01 ms | 334.98 ms | -39.04 ms | -10.44 % | 53.26 ms | 3.79 ms | 0.0000 | yes |
| wpBootstrap | 318.29 ms | 278.73 ms | -39.56 ms | -12.43 % | 53.38 ms | 2.69 ms | 0.0000 | yes |
| wpPlugins | 7.99 ms | 7.92 ms | -0.07 ms | -0.88 % | 0.33 ms | 0.08 ms | 0.6581 | no |
| wpFilesLoaded | 493 | 431 | -62 | -12.58 % | 8 | 8 | 0.0000 | yes |
| wpCacheHits | 730 | 734.5 | 4.5 | 0.62 % | 102.2135509607215 | 97 | 0.7829 | no |
| wpCacheMisses | 51 | 51 | 0 | 0.00 % | 0.6633249580710802 | 1 | 1.0000 | no |
| wpMemoryUsage | 6.61 MB | 6.21 MB | -0.40 MB | -6.02 % | 0.21 MB | 0.21 MB | 0.0000 | yes |
| wpDbQueries | 21 | 21 | 0 | 0.00 % | 1.1608186766243902 | 1 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentyfour, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 405.10 ms | 366.80 ms | -38.30 ms | -9.45 % | 9.69 ms | 4.80 ms | 0.0000 | yes |
| largestContentfulPaint | 476.00 ms | 442.00 ms | -34.00 ms | -7.14 % | 10.97 ms | 6.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 71.10 ms | 72.00 ms | 0.90 ms | 1.27 % | 5.12 ms | 2.30 ms | 0.5656 | no |
| wpBeforeTemplate | 355.25 ms | 315.99 ms | -39.26 ms | -11.05 % | 48.19 ms | 3.14 ms | 0.0000 | yes |
| wpTemplate | 19.18 ms | 19.52 ms | 0.34 ms | 1.77 % | 9.70 ms | 3.55 ms | 0.7895 | no |
| wpTotal | 375.78 ms | 336.80 ms | -38.98 ms | -10.37 % | 48.21 ms | 5.71 ms | 0.0000 | yes |
| wpBootstrap | 318.33 ms | 278.48 ms | -39.85 ms | -12.52 % | 48.01 ms | 2.50 ms | 0.0000 | yes |
| wpPlugins | 7.98 ms | 7.93 ms | -0.05 ms | -0.63 % | 0.30 ms | 0.09 ms | 0.5798 | no |
| wpFilesLoaded | 493 | 431 | -62 | -12.58 % | 7.155417527999327 | 8 | 0.0000 | yes |
| wpCacheHits | 765 | 770 | 5 | 0.65 % | 93.37906403471823 | 58 | 0.7298 | no |
| wpCacheMisses | 51 | 51 | 0 | 0.00 % | 0.913016976841066 | 1 | 1.0000 | no |
| wpMemoryUsage | 6.77 MB | 6.41 MB | -0.36 MB | -5.32 % | 0.24 MB | 0.16 MB | 0.0000 | yes |
| wpDbQueries | 21 | 21 | 0 | 0.00 % | 1.3047605144240069 | 1 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentyfour, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 404.25 ms | 369.10 ms | -35.15 ms | -8.70 % | 4.63 ms | 3.60 ms | 0.0000 | yes |
| largestContentfulPaint | 476.00 ms | 440.00 ms | -36.00 ms | -7.56 % | 7.11 ms | 4.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 71.55 ms | 71.25 ms | -0.30 ms | -0.42 % | 3.82 ms | 1.70 ms | 0.7747 | no |
| wpBeforeTemplate | 355.12 ms | 316.01 ms | -39.11 ms | -11.01 % | 44.11 ms | 3.19 ms | 0.0000 | yes |
| wpTemplate | 19.36 ms | 19.75 ms | 0.39 ms | 2.04 % | 11.17 ms | 4.07 ms | 0.7378 | no |
| wpTotal | 377.13 ms | 340.43 ms | -36.70 ms | -9.73 % | 44.24 ms | 9.24 ms | 0.0000 | yes |
| wpBootstrap | 318.29 ms | 278.48 ms | -39.81 ms | -12.51 % | 43.93 ms | 2.59 ms | 0.0000 | yes |
| wpPlugins | 7.97 ms | 7.92 ms | -0.05 ms | -0.63 % | 0.28 ms | 0.08 ms | 0.4523 | no |
| wpFilesLoaded | 493 | 431 | -62 | -12.58 % | 6.531972647421808 | 8 | 0.0000 | yes |
| wpCacheHits | 775 | 780 | 5 | 0.65 % | 86.83986539730603 | 48 | 0.6794 | no |
| wpCacheMisses | 51 | 51 | 0 | 0.00 % | 0.9255628917943216 | 1 | 1.0000 | no |
| wpMemoryUsage | 6.77 MB | 6.41 MB | -0.36 MB | -5.31 % | 0.25 MB | 0.16 MB | 0.0000 | yes |
| wpDbQueries | 22 | 22 | 0 | 0.00 % | 1.3597385369580752 | 1 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentyfive, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 388.40 ms | 352.20 ms | -36.20 ms | -9.32 % | 12.42 ms | 3.10 ms | 0.0000 | yes |
| largestContentfulPaint | 440.00 ms | 406.00 ms | -34.00 ms | -7.73 % | 15.20 ms | 6.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 52.15 ms | 52.45 ms | 0.30 ms | 0.58 % | 7.65 ms | 2.70 ms | 0.3048 | no |
| wpBeforeTemplate | 355.25 ms | 316.88 ms | -38.36 ms | -10.80 % | 41.10 ms | 3.53 ms | 0.0000 | yes |
| wpTemplate | 19.63 ms | 20.48 ms | 0.84 ms | 4.30 % | 10.46 ms | 4.60 ms | 0.6984 | no |
| wpTotal | 377.25 ms | 340.95 ms | -36.30 ms | -9.62 % | 41.25 ms | 8.29 ms | 0.0000 | yes |
| wpBootstrap | 317.91 ms | 278.97 ms | -38.94 ms | -12.25 % | 40.94 ms | 2.89 ms | 0.0000 | yes |
| wpPlugins | 7.97 ms | 7.92 ms | -0.05 ms | -0.63 % | 0.27 ms | 0.08 ms | 0.4130 | no |
| wpFilesLoaded | 493 | 431 | -62 | -12.58 % | 6.401530429259447 | 8 | 0.0000 | yes |
| wpCacheHits | 765 | 770 | 5 | 0.65 % | 86.80142127116275 | 58 | 0.6517 | no |
| wpCacheMisses | 51 | 51 | 0 | 0.00 % | 6.395406514819516 | 1 | 1.0000 | no |
| wpMemoryUsage | 6.77 MB | 6.41 MB | -0.36 MB | -5.31 % | 0.46 MB | 0.16 MB | 0.0000 | yes |
| wpDbQueries | 21 | 21 | 0 | 0.00 % | 1.3388999444411405 | 1 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentyfive, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 391.80 ms | 353.75 ms | -38.05 ms | -9.71 % | 9.44 ms | 3.70 ms | 0.0000 | yes |
| largestContentfulPaint | 444.00 ms | 414.00 ms | -30.00 ms | -6.76 % | 8.73 ms | 8.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 52.60 ms | 52.60 ms | 0.00 ms | 0.00 % | 6.11 ms | 2.30 ms | 0.4942 | no |
| wpBeforeTemplate | 355.67 ms | 317.31 ms | -38.36 ms | -10.79 % | 38.57 ms | 3.59 ms | 0.0000 | yes |
| wpTemplate | 20.57 ms | 21.02 ms | 0.45 ms | 2.19 % | 9.84 ms | 4.26 ms | 0.5808 | no |
| wpTotal | 377.75 ms | 341.38 ms | -36.37 ms | -9.63 % | 38.73 ms | 8.18 ms | 0.0000 | yes |
| wpBootstrap | 318.04 ms | 279.10 ms | -38.94 ms | -12.24 % | 38.38 ms | 2.83 ms | 0.0000 | yes |
| wpPlugins | 7.97 ms | 7.93 ms | -0.04 ms | -0.56 % | 0.28 ms | 0.09 ms | 0.9228 | no |
| wpFilesLoaded | 490 | 428 | -62 | -12.65 % | 6.224949798994366 | 4 | 0.0000 | yes |
| wpCacheHits | 718 | 723 | 5 | 0.70 % | 84.864980078652 | 79 | 0.6187 | no |
| wpCacheMisses | 52 | 52 | 0 | 0.00 % | 7.838367176906168 | 1.5 | 1.0000 | no |
| wpMemoryUsage | 6.85 MB | 6.50 MB | -0.35 MB | -5.04 % | 0.53 MB | 0.30 MB | 0.0000 | yes |
| wpDbQueries | 22 | 22 | 0 | 0.00 % | 1.2626856299174392 | 1 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentyone, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 387.15 ms | 343.80 ms | -43.35 ms | -11.20 % | 3.29 ms | 2.00 ms | 0.0000 | yes |
| largestContentfulPaint | 432.00 ms | 388.00 ms | -44.00 ms | -10.19 % | 4.47 ms | 4.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 43.25 ms | 45.30 ms | 2.05 ms | 4.74 % | 2.74 ms | 1.75 ms | 0.4868 | no |
| wpBeforeTemplate | 359.82 ms | 316.16 ms | -43.67 ms | -12.14 % | 3.24 ms | 2.05 ms | 0.0000 | yes |
| wpTemplate | 16.66 ms | 16.26 ms | -0.40 ms | -2.37 % | 1.61 ms | 0.34 ms | 0.2398 | no |
| wpTotal | 376.37 ms | 332.69 ms | -43.67 ms | -11.60 % | 3.33 ms | 2.12 ms | 0.0000 | yes |
| wpBootstrap | 321.29 ms | 277.94 ms | -43.35 ms | -13.49 % | 3.16 ms | 1.89 ms | 0.0000 | yes |
| wpPlugins | 8.00 ms | 7.89 ms | -0.11 ms | -1.44 % | 0.13 ms | 0.04 ms | 0.0714 | no |
| wpFilesLoaded | 501 | 439 | -62 | -12.38 % | 0 | 0 | 0.0000 | yes |
| wpCacheHits | 757 | 761 | 4 | 0.53 % | 2.1000000000000005 | 0 | 0.0000 | yes |
| wpCacheMisses | 52 | 52 | 0 | 0.00 % | 0.5999999999999998 | 0 | 1.0000 | no |
| wpMemoryUsage | 6.55 MB | 6.11 MB | -0.43 MB | -6.64 % | 0.00 MB | 0.00 MB | 0.0000 | yes |
| wpDbQueries | 21 | 21 | 0 | 0.00 % | 1.2000000000000002 | 0 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentyone, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 386.60 ms | 344.30 ms | -42.30 ms | -10.94 % | 17.93 ms | 3.80 ms | 0.0000 | yes |
| largestContentfulPaint | 430.00 ms | 388.00 ms | -42.00 ms | -9.77 % | 18.52 ms | 8.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 44.05 ms | 42.95 ms | -1.10 ms | -2.50 % | 4.93 ms | 2.10 ms | 0.9625 | no |
| wpBeforeTemplate | 359.76 ms | 315.91 ms | -43.85 ms | -12.19 % | 12.43 ms | 2.43 ms | 0.0000 | yes |
| wpTemplate | 16.48 ms | 16.45 ms | -0.04 ms | -0.21 % | 1.25 ms | 0.28 ms | 0.6108 | no |
| wpTotal | 376.18 ms | 332.69 ms | -43.48 ms | -11.56 % | 12.72 ms | 2.56 ms | 0.0000 | yes |
| wpBootstrap | 320.65 ms | 277.52 ms | -43.13 ms | -13.45 % | 11.20 ms | 2.34 ms | 0.0000 | yes |
| wpPlugins | 8.01 ms | 7.89 ms | -0.12 ms | -1.50 % | 0.53 ms | 0.04 ms | 0.0911 | no |
| wpFilesLoaded | 501 | 439 | -62 | -12.38 % | 0 | 0 | 0.0000 | yes |
| wpCacheHits | 784.5 | 788.5 | 4 | 0.51 % | 23.69657148196759 | 20.5 | 0.4583 | no |
| wpCacheMisses | 52 | 52 | 0 | 0.00 % | 0.4358898943540675 | 0 | 1.0000 | no |
| wpMemoryUsage | 6.49 MB | 6.05 MB | -0.43 MB | -6.70 % | 0.06 MB | 0.06 MB | 0.0000 | yes |
| wpDbQueries | 22 | 22 | 0 | 0.00 % | 0.8999999999999999 | 0.5 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentythree, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 389.75 ms | 345.05 ms | -44.70 ms | -11.47 % | 4.51 ms | 2.05 ms | 0.0000 | yes |
| largestContentfulPaint | 428.00 ms | 384.00 ms | -44.00 ms | -10.28 % | 5.85 ms | 4.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 35.55 ms | 36.70 ms | 1.15 ms | 3.23 % | 4.64 ms | 2.30 ms | 0.1122 | no |
| wpBeforeTemplate | 359.02 ms | 315.18 ms | -43.85 ms | -12.21 % | 10.66 ms | 3.13 ms | 0.0000 | yes |
| wpTemplate | 16.88 ms | 16.73 ms | -0.14 ms | -0.83 % | 3.28 ms | 0.71 ms | 0.9853 | no |
| wpTotal | 377.06 ms | 333.69 ms | -43.36 ms | -11.50 % | 10.70 ms | 2.87 ms | 0.0000 | yes |
| wpBootstrap | 320.87 ms | 277.11 ms | -43.75 ms | -13.64 % | 9.37 ms | 2.28 ms | 0.0000 | yes |
| wpPlugins | 8.00 ms | 7.88 ms | -0.12 ms | -1.50 % | 0.45 ms | 0.03 ms | 0.0329 | yes |
| wpFilesLoaded | 501 | 439 | -62 | -12.38 % | 7.54247233265651 | 0 | 0.0000 | yes |
| wpCacheHits | 757 | 761 | 4 | 0.53 % | 54.25593669513657 | 48 | 0.6896 | no |
| wpCacheMisses | 52 | 52 | 0 | 0.00 % | 2.825282680055611 | 0 | 1.0000 | no |
| wpMemoryUsage | 6.55 MB | 6.11 MB | -0.43 MB | -6.64 % | 0.19 MB | 0.12 MB | 0.0000 | yes |
| wpDbQueries | 21 | 21 | 0 | 0.00 % | 1.4817407180595243 | 1 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentythree, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 387.45 ms | 346.30 ms | -41.15 ms | -10.62 % | 5.54 ms | 3.90 ms | 0.0000 | yes |
| largestContentfulPaint | 428.00 ms | 384.00 ms | -44.00 ms | -10.28 % | 6.61 ms | 4.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 36.70 ms | 37.40 ms | 0.70 ms | 1.91 % | 4.25 ms | 1.85 ms | 0.2629 | no |
| wpBeforeTemplate | 357.86 ms | 314.91 ms | -42.95 ms | -12.00 % | 9.75 ms | 3.03 ms | 0.0000 | yes |
| wpTemplate | 21.39 ms | 21.58 ms | 0.18 ms | 0.86 % | 3.21 ms | 2.06 ms | 0.9976 | no |
| wpTotal | 376.96 ms | 333.84 ms | -43.12 ms | -11.44 % | 9.67 ms | 3.02 ms | 0.0000 | yes |
| wpBootstrap | 320.67 ms | 277.11 ms | -43.56 ms | -13.58 % | 8.56 ms | 2.61 ms | 0.0000 | yes |
| wpPlugins | 8.00 ms | 7.88 ms | -0.12 ms | -1.50 % | 0.39 ms | 0.04 ms | 0.0116 | yes |
| wpFilesLoaded | 493 | 431 | -62 | -12.58 % | 8 | 8 | 0.0000 | yes |
| wpCacheHits | 725 | 729 | 4 | 0.55 % | 55.35891978714902 | 47 | 0.6504 | no |
| wpCacheMisses | 50 | 50 | 0 | 0.00 % | 3.031501278244823 | 3 | 1.0000 | no |
| wpMemoryUsage | 6.67 MB | 6.28 MB | -0.40 MB | -5.95 % | 0.19 MB | 0.16 MB | 0.0000 | yes |
| wpDbQueries | 21 | 21 | 0 | 0.00 % | 1.3453624047073711 | 1 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentyfour, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 390.35 ms | 351.45 ms | -38.90 ms | -9.97 % | 11.97 ms | 2.55 ms | 0.0000 | yes |
| largestContentfulPaint | 454.00 ms | 416.00 ms | -38.00 ms | -8.37 % | 13.53 ms | 4.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 62.90 ms | 60.90 ms | -2.00 ms | -3.18 % | 4.07 ms | 3.30 ms | 0.1296 | no |
| wpBeforeTemplate | 357.49 ms | 314.37 ms | -43.12 ms | -12.06 % | 10.23 ms | 2.75 ms | 0.0000 | yes |
| wpTemplate | 21.69 ms | 21.84 ms | 0.16 ms | 0.71 % | 4.51 ms | 4.81 ms | 0.8466 | no |
| wpTotal | 377.75 ms | 335.33 ms | -42.42 ms | -11.23 % | 10.70 ms | 3.86 ms | 0.0000 | yes |
| wpBootstrap | 320.33 ms | 277.11 ms | -43.22 ms | -13.49 % | 9.35 ms | 2.19 ms | 0.0000 | yes |
| wpPlugins | 7.99 ms | 7.89 ms | -0.10 ms | -1.25 % | 0.35 ms | 0.04 ms | 0.0059 | yes |
| wpFilesLoaded | 487 | 425 | -62 | -12.73 % | 7.547184905645277 | 2 | 0.0000 | yes |
| wpCacheHits | 678 | 682 | 4 | 0.59 % | 56.21965492601322 | 18 | 0.6004 | no |
| wpCacheMisses | 49 | 49 | 0 | 0.00 % | 2.7249954128401748 | 3 | 1.0000 | no |
| wpMemoryUsage | 6.79 MB | 6.43 MB | -0.36 MB | -5.28 % | 0.19 MB | 0.00 MB | 0.0000 | yes |
| wpDbQueries | 21 | 21 | 0 | 0.00 % | 1.3469966592386193 | 1 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentyfour, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 399.35 ms | 355.00 ms | -44.35 ms | -11.11 % | 3.89 ms | 3.30 ms | 0.0000 | yes |
| largestContentfulPaint | 468.00 ms | 416.00 ms | -52.00 ms | -11.11 % | 8.09 ms | 4.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 64.75 ms | 64.10 ms | -0.65 ms | -1.00 % | 6.54 ms | 4.25 ms | 0.7731 | no |
| wpBeforeTemplate | 357.49 ms | 314.85 ms | -42.64 ms | -11.93 % | 9.47 ms | 2.97 ms | 0.0000 | yes |
| wpTemplate | 21.84 ms | 22.09 ms | 0.25 ms | 1.12 % | 4.59 ms | 4.57 ms | 0.8829 | no |
| wpTotal | 378.55 ms | 337.30 ms | -41.25 ms | -10.90 % | 10.15 ms | 4.42 ms | 0.0000 | yes |
| wpBootstrap | 320.33 ms | 277.61 ms | -42.72 ms | -13.34 % | 8.70 ms | 2.23 ms | 0.0000 | yes |
| wpPlugins | 7.99 ms | 7.90 ms | -0.09 ms | -1.13 % | 0.34 ms | 0.05 ms | 0.0013 | yes |
| wpFilesLoaded | 487 | 425 | -62 | -12.73 % | 7.118052168020874 | 2 | 0.0000 | yes |
| wpCacheHits | 678 | 682 | 4 | 0.59 % | 54.052402557353744 | 12.5 | 0.5379 | no |
| wpCacheMisses | 49 | 49 | 0 | 0.00 % | 2.487971060924945 | 3 | 1.0000 | no |
| wpMemoryUsage | 6.79 MB | 6.43 MB | -0.36 MB | -5.28 % | 0.18 MB | 0.00 MB | 0.0000 | yes |
| wpDbQueries | 21 | 21 | 0 | 0.00 % | 1.3034143197344767 | 1 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentyfive, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 396.00 ms | 355.45 ms | -40.55 ms | -10.24 % | 5.57 ms | 3.70 ms | 0.0000 | yes |
| largestContentfulPaint | 456.00 ms | 412.00 ms | -44.00 ms | -9.65 % | 11.06 ms | 6.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 60.30 ms | 58.10 ms | -2.20 ms | -3.65 % | 7.68 ms | 2.10 ms | 0.4609 | no |
| wpBeforeTemplate | 357.61 ms | 314.91 ms | -42.70 ms | -11.94 % | 8.93 ms | 2.85 ms | 0.0000 | yes |
| wpTemplate | 22.33 ms | 22.51 ms | 0.18 ms | 0.78 % | 4.90 ms | 4.56 ms | 0.8859 | no |
| wpTotal | 379.31 ms | 338.63 ms | -40.68 ms | -10.72 % | 9.93 ms | 4.94 ms | 0.0000 | yes |
| wpBootstrap | 320.14 ms | 277.54 ms | -42.61 ms | -13.31 % | 8.20 ms | 2.26 ms | 0.0000 | yes |
| wpPlugins | 8.00 ms | 7.90 ms | -0.10 ms | -1.25 % | 0.32 ms | 0.05 ms | 0.0004 | yes |
| wpFilesLoaded | 487 | 425 | -62 | -12.73 % | 6.627093431281836 | 2 | 0.0000 | yes |
| wpCacheHits | 678 | 682 | 4 | 0.59 % | 51.16480973385949 | 18 | 0.4720 | no |
| wpCacheMisses | 49 | 49 | 0 | 0.00 % | 9.083018467987166 | 3 | 1.0000 | no |
| wpMemoryUsage | 6.79 MB | 6.43 MB | -0.36 MB | -5.28 % | 0.50 MB | 0.00 MB | 0.0000 | yes |
| wpDbQueries | 21 | 21 | 0 | 0.00 % | 2.277843591770829 | 1 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentyfive, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 393.80 ms | 356.50 ms | -37.30 ms | -9.47 % | 4.37 ms | 1.50 ms | 0.0000 | yes |
| largestContentfulPaint | 452.00 ms | 416.00 ms | -36.00 ms | -7.96 % | 8.45 ms | 4.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 56.35 ms | 59.15 ms | 2.80 ms | 4.97 % | 7.02 ms | 1.55 ms | 0.2761 | no |
| wpBeforeTemplate | 357.14 ms | 315.14 ms | -42.00 ms | -11.76 % | 8.49 ms | 2.85 ms | 0.0000 | yes |
| wpTemplate | 25.97 ms | 26.13 ms | 0.16 ms | 0.62 % | 4.92 ms | 3.44 ms | 0.9798 | no |
| wpTotal | 380.58 ms | 339.99 ms | -40.59 ms | -10.67 % | 9.62 ms | 5.41 ms | 0.0000 | yes |
| wpBootstrap | 319.93 ms | 277.61 ms | -42.32 ms | -13.23 % | 7.82 ms | 2.13 ms | 0.0000 | yes |
| wpPlugins | 8.00 ms | 7.90 ms | -0.09 ms | -1.19 % | 0.31 ms | 0.05 ms | 0.0002 | yes |
| wpFilesLoaded | 488 | 426 | -62 | -12.70 % | 6.224949798994366 | 2 | 0.0000 | yes |
| wpCacheHits | 713 | 717.5 | 4.5 | 0.63 % | 48.873073811148814 | 42.5 | 0.4129 | no |
| wpCacheMisses | 51.5 | 51.5 | 0 | 0.00 % | 11.039814310032577 | 3 | 1.0000 | no |
| wpMemoryUsage | 6.79 MB | 6.43 MB | -0.36 MB | -5.30 % | 0.60 MB | 0.16 MB | 0.0000 | yes |
| wpDbQueries | 22 | 22 | 0 | 0.00 % | 2.7381563140186125 | 2 | 1.0000 | no |
| wpExtObjCache | no | no |  |  |  |  |  |  |
