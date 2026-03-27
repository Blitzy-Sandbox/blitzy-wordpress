#!/usr/bin/env bash
##
# Baseline Benchmark Run Script
#
# Captures pre-optimization performance metrics by running the Docker-based
# benchmark harness and storing results as the baseline reference.
#
# Usage:
#   ./benchmarks/run-baseline.sh
#
# Output:
#   benchmarks/results/baseline-metrics.json
#
# The baseline metrics are used by generate-diff-report.js to compute
# percentage deltas against the optimized run.
#
# Prerequisites:
#   - Docker and Docker Compose installed
#   - Repository root as the working directory
#
# @since 7.0.0
##

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
RESULTS_DIR="$SCRIPT_DIR/results"
BASELINE_FILE="$RESULTS_DIR/baseline-metrics.json"

# Ensure results directory exists.
mkdir -p "$RESULTS_DIR"

echo "=============================================="
echo "  WordPress Baseline Performance Benchmark"
echo "=============================================="
echo ""
echo "This script captures pre-optimization metrics."
echo "Run this BEFORE applying performance optimizations,"
echo "or against a baseline branch/tag."
echo ""
echo "Results will be saved to: $BASELINE_FILE"
echo ""

cd "$REPO_ROOT"

# Clean up any prior benchmark containers to ensure a fresh environment.
echo "[1/5] Cleaning up prior benchmark containers..."
docker compose -f docker-compose.benchmark.yml down -v 2>/dev/null || true

# Start the benchmark environment.
echo "[2/5] Starting Docker benchmark environment..."
docker compose -f docker-compose.benchmark.yml up -d mysql-benchmark php-benchmark wordpress-benchmark cli-benchmark

# Wait for WordPress to be ready.
echo "[3/5] Waiting for WordPress to become available..."
MAX_WAIT=180
WAITED=0
while [ "$WAITED" -lt "$MAX_WAIT" ]; do
    if [ -f "$REPO_ROOT/benchmarks/.wp-ready" ]; then
        echo "  WordPress is ready (seeded and installed)."
        break
    fi
    # Also check HTTP availability as a fallback.
    if curl -s -o /dev/null -w "%{http_code}" http://localhost:8890/ 2>/dev/null | grep -q "200\|301\|302"; then
        echo "  WordPress HTTP endpoint is responding."
        # Give CLI seeder a few more seconds to finish.
        sleep 5
        break
    fi
    sleep 2
    WAITED=$((WAITED + 2))
    if [ "$((WAITED % 20))" -eq 0 ]; then
        echo "  Still waiting... (${WAITED}s elapsed)"
    fi
done

if [ "$WAITED" -ge "$MAX_WAIT" ]; then
    echo "  WARNING: Timed out waiting for WordPress. Proceeding with best effort."
fi

# Execute baseline benchmark measurements.
echo "[4/5] Running baseline benchmark measurements..."
echo ""

# Use the benchmark runner with BENCHMARK_MODE=baseline to save metrics
# to the baseline file instead of the optimized file.
BENCHMARK_URL="http://localhost:8890" \
BENCHMARK_RUNS="${BENCHMARK_RUNS:-3}" \
BENCHMARK_ITERATIONS="${BENCHMARK_ITERATIONS:-5}" \
BENCHMARK_MODE="baseline" \
node "$SCRIPT_DIR/run-benchmark.js"

# Verify baseline file was created.
if [ -f "$BASELINE_FILE" ]; then
    echo ""
    echo "[5/5] Baseline metrics captured successfully."
    echo "  File: $BASELINE_FILE"
    echo ""
    echo "  Preview:"
    cat "$BASELINE_FILE" | head -20
    echo ""
else
    echo ""
    echo "[5/5] ERROR: Baseline file was not created."
    echo "  Check the benchmark runner output above for errors."
    exit 1
fi

# Clean up benchmark containers.
echo "Cleaning up benchmark containers..."
docker compose -f docker-compose.benchmark.yml down -v 2>/dev/null || true
rm -f "$REPO_ROOT/benchmarks/.wp-ready"

echo "=============================================="
echo "  Baseline capture complete."
echo "  Next: Apply optimizations and run run-optimized.sh"
echo "=============================================="
