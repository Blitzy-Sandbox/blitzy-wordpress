# Benchmark Diff Report

- Generated at: 1970-01-01T00:00:00.000Z
- Baseline: `before-performance-results.json` (present)
- Optimized: `performance-results.json`
- Repetitions: 2
- Iterations: 20

## KPI Targets

| Target | Metric | Before | After | Reduction % | Threshold | Status | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| Front-end TTFB (uncached) | timeToFirstByte | 53.72 ms | 41.90 ms | 22.00 % | >= 20% | PASS | 0.0000 | yes |
| Admin DOMContentLoaded | domContentLoaded | 50.66 ms | 42.05 ms | 17.00 % | >= 15% | PASS | 0.0000 | yes |
| Admin JS transfer size (gzipped) | adminJsTransferSize | 512.00 KB | 420.00 KB | 17.97 % | >= 30% | FAIL | 0.0000 | yes |
| PHP memory per front-end request | wpMemoryUsage | 34.00 MB | 30.00 MB | 11.76 % | >= 10% | PASS | 0.0000 | yes |
| DB queries per front-end page | wpDbQueries | 24 | 20 | 16.67 % | >= 15% | PASS | 0.0000 | yes |
| PHP files loaded per front-end request | wpFilesLoaded | 600 | 417 | 30.50 % | >= 30% | PASS | 0.0000 | yes |

Targets met: 5 / 6

## Per-Suite Metrics

### Admin › Locale: en_US (scope: admin)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| domContentLoaded | 50.66 ms | 42.05 ms | -8.61 ms | -20.48 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| adminJsTransferSize | 512.00 KB | 420.00 KB | -92.00 KB | -21.90 % | 8.19 KB | 8.40 KB | 0.0000 | yes |
| timeToFirstByte | 60.40 ms | 52.00 ms | -8.40 ms | -16.15 % | 1.01 ms | 1.04 ms | 0.0000 | yes |
| wpMemoryUsage | 36.00 MB | 32.00 MB | -4.00 MB | -12.50 % | 0.62 MB | 0.64 MB | 0.0000 | yes |
| wpDbQueries | 34 | 29 | -5 | -17.24 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 642 | 447 | -195 | -43.62 % | 8.772114910328067 | 9 | 0.0000 | yes |
| wpBootstrap | 9.10 ms | 6.80 ms | -2.30 ms | -33.82 % | 0.13 ms | 0.14 ms | 0.0000 | yes |
| wpPlugins | 3.40 ms | 2.70 ms | -0.70 ms | -25.93 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 150 | 205 | 55 | 26.83 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 52 | 26 | -26 | -100.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Admin › Locale: de_DE (scope: admin)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| domContentLoaded | 50.66 ms | 42.05 ms | -8.61 ms | -20.48 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| adminJsTransferSize | 512.00 KB | 420.00 KB | -92.00 KB | -21.90 % | 8.19 KB | 8.40 KB | 0.0000 | yes |
| timeToFirstByte | 60.40 ms | 52.00 ms | -8.40 ms | -16.15 % | 1.01 ms | 1.04 ms | 0.0000 | yes |
| wpMemoryUsage | 36.00 MB | 32.00 MB | -4.00 MB | -12.50 % | 0.62 MB | 0.64 MB | 0.0000 | yes |
| wpDbQueries | 34 | 29 | -5 | -17.24 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 642 | 447 | -195 | -43.62 % | 8.772114910328067 | 9 | 0.0000 | yes |
| wpBootstrap | 9.10 ms | 6.80 ms | -2.30 ms | -33.82 % | 0.13 ms | 0.14 ms | 0.0000 | yes |
| wpPlugins | 3.40 ms | 2.70 ms | -0.70 ms | -25.93 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 150 | 205 | 55 | 26.83 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 52 | 26 | -26 | -100.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentyone, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentyone, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentythree, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentythree, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentyfour, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentyfour, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentyfive, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Homepage › Theme: twentytwentyfive, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentyone, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentyone, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentythree, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentythree, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentyfour, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentyfour, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentyfive, Locale: en_US (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |

### Single Post › Theme: twentytwentyfive, Locale: de_DE (scope: front-end)

| Metric | Before | After | Diff abs. | Diff % | STD | MAD | p-value | Significant |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| timeToFirstByte | 53.72 ms | 41.90 ms | -11.82 ms | -28.21 % | 0.82 ms | 0.84 ms | 0.0000 | yes |
| wpMemoryUsage | 34.00 MB | 30.00 MB | -4.00 MB | -13.33 % | 0.58 MB | 0.60 MB | 0.0000 | yes |
| wpDbQueries | 24 | 20 | -4 | -20.00 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| wpFilesLoaded | 600 | 417 | -183 | -43.88 % | 7.797435475847171 | 8 | 0.0000 | yes |
| wpBootstrap | 8.50 ms | 6.20 ms | -2.30 ms | -37.10 % | 0.12 ms | 0.12 ms | 0.0000 | yes |
| wpPlugins | 3.00 ms | 2.40 ms | -0.60 ms | -25.00 % | 0.05 ms | 0.05 ms | 0.0000 | yes |
| wpCacheHits | 128 | 176 | 48 | 27.27 % | 3.8987177379235853 | 4 | 0.0000 | yes |
| wpCacheMisses | 44 | 21 | -23 | -109.52 % | 0.9746794344808963 | 1 | 0.0000 | yes |
| largestContentfulPaint | 120.00 ms | 105.00 ms | -15.00 ms | -14.29 % | 1.95 ms | 2.00 ms | 0.0000 | yes |
| lcpMinusTtfb | 66.28 ms | 63.10 ms | -3.18 ms | -5.04 % | 1.23 ms | 1.26 ms | 0.0000 | yes |
| wpExtObjCache | no | no |  |  |  |  |  |  |
