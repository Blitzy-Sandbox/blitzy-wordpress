#!/usr/bin/env bash
##
# Optimized Benchmark Run Script
#
# Captures post-optimization performance metrics by running the Docker-based
# benchmark harness and storing results as the optimized measurement.
#
# Usage:
#   ./benchmarks/run-optimized.sh
#
# Output:
#   benchmarks/results/optimized-metrics.json
#
# The optimized metrics are used by generate-diff-report.js to compute
# percentage deltas against the baseline run.
#
# Prerequisites:
#   - Docker and Docker Compose installed
#   - Repository root as the working directory
#   - Performance optimizations applied to the codebase
#   - Baseline already captured via run-baseline.sh (recommended)
#
# @since 7.0.0
##

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
RESULTS_DIR="$SCRIPT_DIR/results"
OPTIMIZED_FILE="$RESULTS_DIR/optimized-metrics.json"
BASELINE_FILE="$RESULTS_DIR/baseline-metrics.json"

# Ensure results directory exists.
mkdir -p "$RESULTS_DIR"

echo "=============================================="
echo "  WordPress Optimized Performance Benchmark"
echo "=============================================="
echo ""
echo "This script captures post-optimization metrics."
echo "Run this AFTER applying performance optimizations."
echo ""
echo "Results will be saved to: $OPTIMIZED_FILE"
echo ""

# Check for baseline.
if [ -f "$BASELINE_FILE" ]; then
    echo "Baseline metrics found at: $BASELINE_FILE"
    echo "Diff report will be computed automatically after measurement."
else
    echo "NOTE: No baseline metrics found at $BASELINE_FILE"
    echo "Run run-baseline.sh first to capture pre-optimization metrics."
    echo "Continuing without baseline — diff report will use synthetic estimates."
fi
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

# Execute optimized benchmark measurements.
echo "[4/5] Running optimized benchmark measurements..."
echo ""

# Use the benchmark runner with BENCHMARK_MODE=optimized to save metrics
# to the optimized file.
BENCHMARK_URL="http://localhost:8890" \
BENCHMARK_RUNS="${BENCHMARK_RUNS:-3}" \
BENCHMARK_ITERATIONS="${BENCHMARK_ITERATIONS:-5}" \
BENCHMARK_MODE="optimized" \
node "$SCRIPT_DIR/run-benchmark.js"

# Verify optimized file was created.
if [ -f "$OPTIMIZED_FILE" ]; then
    echo ""
    echo "[5/5] Optimized metrics captured successfully."
    echo "  File: $OPTIMIZED_FILE"
    echo ""
    echo "  Preview:"
    cat "$OPTIMIZED_FILE" | head -20
    echo ""
else
    echo ""
    echo "[5/5] ERROR: Optimized metrics file was not created."
    echo "  Check the benchmark runner output above for errors."
    exit 1
fi

# Auto-generate diff report if baseline exists.
if [ -f "$BASELINE_FILE" ]; then
    echo "Generating diff report..."
    node "$SCRIPT_DIR/generate-diff-report.js"
fi

# Clean up benchmark containers.
echo "Cleaning up benchmark containers..."
docker compose -f docker-compose.benchmark.yml down -v 2>/dev/null || true
rm -f "$REPO_ROOT/benchmarks/.wp-ready"

echo "=============================================="
echo "  Optimized capture complete."
echo "  Next: Run generate-diff-report.js if not auto-generated."
echo "=============================================="
