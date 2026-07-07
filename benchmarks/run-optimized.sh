#!/usr/bin/env bash
#
# benchmarks/run-optimized.sh - OPTIMIZED (post-optimization) measurement runner.
#
# Feature F-011 (performance benchmark harness). This script stands up the pinned,
# isolated Docker benchmark environment defined in the repository-root
# "docker-compose.benchmark.yml", runs the existing Playwright performance suite
# against WHATEVER CODE IS CURRENTLY CHECKED OUT, and emits the aggregated raw
# metrics as the OPTIMIZED ("after") artifact:
#
#     benchmarks/results/performance-results.json
#
# It is the exact mirror-sibling of "benchmarks/run-baseline.sh"; the two scripts
# differ ONLY in the run LABEL ("optimized" vs "baseline") and the output file
# name ("performance-results.json" vs "before-performance-results.json"). Keeping
# them structurally identical is what guarantees the measured before/after delta
# is attributable to CODE changes rather than to harness differences - the
# evidence-first mandate in mechanical form.
#
# Baseline-vs-optimized workflow (the operator drives source control; this script
# NEVER runs "git checkout"):
#     1. Check out the PRE-optimization ref and run benchmarks/run-baseline.sh
#        -> writes benchmarks/results/before-performance-results.json
#     2. Check out the OPTIMIZED ref and run this script
#        -> writes benchmarks/results/performance-results.json
#     3. Run "node benchmarks/generate-diff-report.js" to diff the two and emit
#        benchmarks/results/benchmark-report.json
#
# Usage:
#     chmod +x benchmarks/run-optimized.sh        # once, to run it directly, or
#     bash benchmarks/run-optimized.sh [options]  # run via bash without the +x bit
#
# Options:
#     --keep-up         Leave the benchmark stack running after the run.
#     --clean-volumes   On teardown, also drop the isolated mysql-benchmark volume.
#     --no-up           Assume the benchmark stack is already running; skip bring-up.
#     -h, --help        Print usage information and exit (touches no Docker).
#
# Isolation: uses the dedicated Compose project "blitzy-wp-benchmark" on port 8890
# with its own network ("wpbenchnet") and volume ("mysql-benchmark"), so it never
# collides with the development environment ("docker-compose.yml", port 8889);
# both stacks can run side by side.
#
# This is a TEST/DEVELOPMENT-ONLY tool. Its "off" state is simply not running it;
# it is never part of a production deployment.
#
set -euo pipefail

# -----------------------------------------------------------------------------
# Paths
# -----------------------------------------------------------------------------
# Resolve the script and repository-root directories so the script works from any
# caller CWD, then operate from the repository root (every "docker compose" and
# "node" invocation below is relative to it).
script_dir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
repo_root="$( cd "$script_dir/.." && pwd )"
cd "$repo_root"

# -----------------------------------------------------------------------------
# Configuration (pinned; mirrors run-baseline.sh)
# -----------------------------------------------------------------------------
# The isolated benchmark compose file and its pinned identifiers. These MUST match
# the constants baked into docker-compose.benchmark.yml so bring-up, teardown, and
# the readiness poll all target the same isolated stack (never the dev stack).
COMPOSE_FILE="docker-compose.benchmark.yml"
COMPOSE_PROJECT="blitzy-wp-benchmark"
BENCH_PORT="8890"
BENCH_URL="http://localhost:${BENCH_PORT}"

# The two values below are the ONLY substantive differences from run-baseline.sh
# (which uses LABEL="baseline" and OUTPUT_FILE=".../before-performance-results.json").
LABEL="optimized"
ARTIFACTS_DIR="${repo_root}/benchmarks/results"
OUTPUT_FILE="${ARTIFACTS_DIR}/performance-results.json"

# Readiness poll tuning: up to ~5 minutes (60 attempts x 5s), consistent with the
# performance suite's 600s per-test timeout.
READINESS_ATTEMPTS="60"
READINESS_SLEEP="5"

# Convenience wrapper so every Compose call targets the isolated project and file.
compose() {
	docker compose -p "$COMPOSE_PROJECT" -f "$COMPOSE_FILE" "$@"
}

# -----------------------------------------------------------------------------
# Usage
# -----------------------------------------------------------------------------
usage() {
	cat <<'USAGE'
Usage: bash benchmarks/run-optimized.sh [options]

Run the OPTIMIZED (post-optimization) performance measurement against the pinned,
isolated Docker benchmark environment (docker-compose.benchmark.yml, Compose
project blitzy-wp-benchmark, http://localhost:8890) and write the aggregated raw
metrics to benchmarks/results/performance-results.json.

This is the mirror-sibling of benchmarks/run-baseline.sh (it differs only in the
run label and the output file name). Run run-baseline.sh against the
pre-optimization checkout and this script against the optimized checkout, then run
"node benchmarks/generate-diff-report.js" to diff the two.

Options:
--keep-up         Leave the benchmark stack running after the run.
--clean-volumes   On teardown, also drop the isolated mysql-benchmark volume.
--no-up           Assume the benchmark stack is already running; skip bring-up.
-h, --help        Print this help and exit.

Test/development-only. Uses the isolated project blitzy-wp-benchmark on port 8890;
never the development docker-compose.yml (port 8889).
USAGE
}

# -----------------------------------------------------------------------------
# Options
# -----------------------------------------------------------------------------
KEEP_UP="false"
CLEAN_VOLUMES="false"
NO_UP="false"

# Tracks whether THIS invocation brought the stack up, so teardown only tears down
# what we started (a "--no-up" run leaves an externally-managed stack untouched).
STACK_STARTED="false"

# -----------------------------------------------------------------------------
# Teardown (deterministic; runs on success and failure via the EXIT trap)
# -----------------------------------------------------------------------------
cleanup() {
	# Capture the triggering exit status FIRST so the trap never masks it.
	local exit_code="$?"

	if [ "$KEEP_UP" = "true" ]; then
		if [ "$STACK_STARTED" = "true" ] || [ "$NO_UP" = "true" ]; then
			echo "==> --keep-up set; leaving the '${COMPOSE_PROJECT}' benchmark stack running."
		fi
	elif [ "$STACK_STARTED" = "true" ]; then
		echo "==> Tearing down the '${COMPOSE_PROJECT}' benchmark stack..."
		if [ "$CLEAN_VOLUMES" = "true" ]; then
			# "-v" drops ONLY this project's isolated mysql-benchmark volume; the
			# development stack's "mysql" volume is a different project and is safe.
			compose down -v || true
		else
			compose down || true
		fi
	elif [ "$NO_UP" = "true" ]; then
		echo "==> --no-up set; leaving the externally-managed benchmark stack as-is."
	fi

	exit "$exit_code"
}

# Install the trap early so any failure below still triggers cleanup. It is a
# no-op until STACK_STARTED becomes "true", so an early "--help"/error exit (before
# bring-up) never touches Docker.
trap cleanup EXIT

# Parse options. Unknown flags print usage to stderr and exit non-zero.
while [ "$#" -gt 0 ]; do
	case "$1" in
		--keep-up)
			KEEP_UP="true"
			;;
		--clean-volumes)
			CLEAN_VOLUMES="true"
			;;
		--no-up)
			NO_UP="true"
			;;
		-h|--help)
			usage
			exit 0
			;;
		*)
			echo "ERROR: unknown option: $1" >&2
			echo >&2
			usage >&2
			exit 1
			;;
	esac
	shift
done

# -----------------------------------------------------------------------------
# Bring up the isolated benchmark environment
# -----------------------------------------------------------------------------
mkdir -p "$ARTIFACTS_DIR"

if [ "$NO_UP" = "true" ]; then
	echo "==> --no-up set; assuming the '${COMPOSE_PROJECT}' benchmark stack is already running."
else
	echo "==> Bringing up the '${COMPOSE_PROJECT}' benchmark stack (${COMPOSE_FILE})..."
	# Mark the stack as started BEFORE the up call so a partial/failed bring-up is
	# still torn down by the EXIT trap.
	STACK_STARTED="true"
	# Prefer "--wait" (blocks until healthchecks pass; the mysql service defines a
	# healthcheck). Fall back to a plain "up -d" for older Compose releases that do
	# not support "--wait"; the HTTP readiness poll below covers both paths.
	if ! compose up -d --wait; then
		echo "==> 'compose up -d --wait' failed or is unsupported; retrying with 'compose up -d'..." >&2
		compose up -d
	fi
fi

# -----------------------------------------------------------------------------
# Wait for the web server to answer over HTTP
# -----------------------------------------------------------------------------
# Container "up" is not the same as "serving"; poll the real HTTP endpoint.
echo "==> Waiting for ${BENCH_URL} to respond..."
ready="false"
for (( attempt = 1; attempt <= READINESS_ATTEMPTS; attempt++ )); do
	if curl -fsS -o /dev/null "$BENCH_URL"; then
		ready="true"
		echo "==> Benchmark server is responding (attempt ${attempt})."
		break
	fi
	sleep "$READINESS_SLEEP"
done

if [ "$ready" != "true" ]; then
	echo "ERROR: ${BENCH_URL} did not respond after $(( READINESS_ATTEMPTS * READINESS_SLEEP ))s." >&2
	exit 1
fi

# -----------------------------------------------------------------------------
# Ensure WordPress is installed (idempotent)
# -----------------------------------------------------------------------------
# "wp core is-installed" exits non-zero when WordPress is not yet installed. The
# "if !" guard keeps that expected non-zero status from tripping "set -e".
echo "==> Ensuring WordPress is installed in the benchmark environment..."
if ! compose exec -T cli wp core is-installed; then
	echo "==> Installing WordPress..."
	compose exec -T cli wp core install \
		--url="$BENCH_URL" \
		--title="WordPress Benchmark" \
		--admin_user=admin \
		--admin_email=admin@example.com \
		--admin_password=password \
		--skip-email
else
	echo "==> WordPress is already installed; skipping installation."
fi

# -----------------------------------------------------------------------------
# Export the measurement environment
# -----------------------------------------------------------------------------
# tests/performance/playwright.config.js is intentionally left unmodified, and its
# "webServer.command" points at the DEV environment ("npm run env:start"). We reuse
# the already-running benchmark stack instead of starting the dev env by:
#   * Exporting WP_BASE_URL so the @wordpress/scripts Playwright base config derives
#     "webServer.port" from it (here 8890, the benchmark port) and points every test
#     at the benchmark server, and
#   * Relying on that base config's "reuseExistingServer: true", which makes
#     Playwright reuse a server already listening on that port instead of running
#     the dev "env:start" command.
# WP_ARTIFACTS_PATH points the performance reporter at benchmarks/results so it
# writes performance-results.json exactly where run-benchmark.js reads it.
export WP_ARTIFACTS_PATH="$ARTIFACTS_DIR"
export WP_BASE_URL="$BENCH_URL"

# -----------------------------------------------------------------------------
# Run the benchmark orchestrator
# -----------------------------------------------------------------------------
# run-benchmark.js drives "npm run test:performance" (inheriting the exported
# environment), aggregates the per-metric samples, and writes the labeled JSON
# artifact. TEST_RUNS is left unset so the config default (20) applies unless the
# caller overrides it in the environment. A non-zero exit here aborts the script
# (set -e) and propagates through the EXIT trap.
echo "==> Running the benchmark orchestrator (label: ${LABEL})..."
node benchmarks/run-benchmark.js --label "$LABEL" --output "$OUTPUT_FILE"

# -----------------------------------------------------------------------------
# Summary
# -----------------------------------------------------------------------------
echo
echo "==> Optimized measurement complete."
echo "    Results written to: ${OUTPUT_FILE}"
echo "    Next: run 'node benchmarks/generate-diff-report.js' to diff the baseline"
echo "    (before-performance-results.json) against these optimized results and"
echo "    emit benchmarks/results/benchmark-report.json."
