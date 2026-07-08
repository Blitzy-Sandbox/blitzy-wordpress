#!/usr/bin/env bash
#
# run-optimized.sh — OPTIMIZED performance measurement runner.
#
# Part of the isolated benchmark harness (Feature F-011). This script stands up
# the pinned, isolated Docker benchmark environment defined in
# "docker-compose.benchmark.yml", ensures WordPress is installed, drives the
# existing Playwright performance suite against it (via the Node orchestrator
# "benchmarks/run-benchmark.js"), and emits the raw aggregated metrics to
# "benchmarks/results/performance-results.json" — the exact file name
# that "benchmarks/generate-diff-report.js" (and
# "tests/performance/compare-results.js") read as the OPTIMIZED input.
#
# It is the mirror-sibling of "benchmarks/run-baseline.sh"; the two scripts are
# intentionally identical except for their LABEL and OUTPUT_FILE (and this
# header text). Any structural change made here must be mirrored there.
#
# Baseline vs. optimized workflow
# -------------------------------
# This runner measures WHATEVER CODE IS CURRENTLY CHECKED OUT and labels the
# output as the optimized run. The intended workflow is:
#   1. Check out the pre-optimization revision, then run
#      "benchmarks/run-baseline.sh" to capture "before-performance-results.json".
#   2. Check out the optimized revision, then run this script to capture
#      "performance-results.json".
#   3. Run "node benchmarks/generate-diff-report.js" to diff the two.
# This script deliberately does NOT touch source control (no "git checkout"):
# its sole responsibility is the environment + measurement, not the revision.
#
# Prerequisites
# -------------
# The wordpress-develop checkout must have its JavaScript/CSS assets built
# before it can render (e.g. "npm run build"). An unbuilt tree returns HTTP 500
# ("running WordPress without JavaScript and CSS files") and the readiness poll
# below correctly refuses to measure it. Building the tree — like selecting the
# revision to measure — is part of preparing the checkout and is left to the
# operator, keeping this script strictly measurement-only.
#
# Usage:
#   bash benchmarks/run-optimized.sh [options]
#   # or, once marked executable with "chmod +x": ./benchmarks/run-optimized.sh
#
# Options:
#   --keep-up         Leave the benchmark stack running after the run (useful
#                     for debugging or for chaining an optimized run afterward).
#   --no-up           Assume the benchmark stack is already running; skip the
#                     bring-up step (and, since this invocation does not own the
#                     stack, skip teardown too).
#   --clean-volumes   On teardown, also remove the isolated benchmark database
#                     volume ("mysql-benchmark"); the dev "mysql" volume is a
#                     different project and is never touched.
#   -h, --help        Print this help and exit 0 without touching Docker.
#
# Environment overrides (all optional):
#   BENCHMARK_MYSQL_ROOT_PASSWORD  Database root password (default: "password");
#                                  must match docker-compose.benchmark.yml.
#   BENCHMARK_READINESS_ATTEMPTS   HTTP readiness poll attempts (default: 60).
#   BENCHMARK_READINESS_INTERVAL   Seconds between poll attempts (default: 5).
#   BENCHMARK_DB_ATTEMPTS          Database readiness poll attempts (default: 60).
#   BENCHMARK_DB_INTERVAL          Seconds between DB poll attempts (default: 5).
#   TEST_RUNS                      Per-suite Playwright iteration count; passed
#                                  through untouched (Playwright default: 20).
#   BENCHMARK_RUNS                 How many times run-benchmark.js invokes the
#                                  whole suite (default: 1).
#
# This is a TEST/DEVELOPMENT-ONLY tool. Its "off" state is simply not running
# it; it is never part of a production deployment.
#
set -euo pipefail

# ---------------------------------------------------------------------------
# Paths — resolve the repository root from this script's location so the script
# works regardless of the caller's current directory. All docker compose / node
# invocations run from the repository root.
# ---------------------------------------------------------------------------
script_dir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
repo_root="$( cd "${script_dir}/.." && pwd )"
cd "${repo_root}"

# ---------------------------------------------------------------------------
# Configuration constants. These MUST stay aligned with docker-compose.benchmark.yml
# (project name, published port, isolated network/volume) and with
# benchmarks/run-benchmark.js (label -> output file name). The LABEL and
# OUTPUT_FILE below are the ONLY meaningful difference from run-baseline.sh.
# ---------------------------------------------------------------------------
COMPOSE_FILE="docker-compose.benchmark.yml"
COMPOSE_PROJECT="blitzy-wp-benchmark"
BENCH_PORT="8890"
BENCH_URL="http://localhost:${BENCH_PORT}"
LABEL="optimized"
ARTIFACTS_DIR="${repo_root}/benchmarks/results"
OUTPUT_FILE="${ARTIFACTS_DIR}/performance-results.json"

# WordPress provisioning constants. WP-CLI runs inside the "cli" service; --path
# points at the WordPress source root (the nginx docroot is "src", mounted at
# /var/www), and the config file is written where the container's WP_CONFIG_PATH
# expects it. The database coordinates match docker-compose.benchmark.yml
# (service name "mysql") and tools/local-env/mysql-init.sql (database
# "wordpress_develop"). "password" is the well-known throwaway local credential
# used across the WordPress-develop tooling (docker-compose*.yml, .devcontainer/
# setup.sh) — it is NOT a real secret, and the DB password is overridable via
# BENCHMARK_MYSQL_ROOT_PASSWORD to stay in sync with the compose file. The admin
# credentials are fixed because the performance suite's global setup
# authenticates over REST as this admin user (admin / password).
WP_PATH="/var/www/src"
# The nginx docroot is "src", so WordPress (and WP-CLI with --path=src) resolve
# ABSPATH/wp-config.php = src/wp-config.php BEFORE any config one directory up.
# We therefore write the benchmark config at that resolved path (writing to
# /var/www/wp-config.php would be shadowed by any developer src/wp-config.php the
# project setup creates, whose DEV database host is unreachable from inside the
# benchmark network — the exact cause of a served-site "database error" 500).
WP_CONFIG_PATH_IN_CONTAINER="/var/www/src/wp-config.php"
# Host-side path to that served config, plus a slot remembering a pre-existing
# file we temporarily displace so cleanup() can restore it. src/wp-config.php is
# gitignored, so writing/restoring it never dirties the tracked tree.
HOST_WP_CONFIG="${repo_root}/src/wp-config.php"
HOST_WP_CONFIG_BACKUP=""
DB_NAME="wordpress_develop"
DB_USER="root"
DB_PASS="${BENCHMARK_MYSQL_ROOT_PASSWORD:-password}"
DB_HOST="mysql"
WP_TITLE="WordPress Benchmark"
WP_ADMIN_USER="admin"
WP_ADMIN_EMAIL="admin@example.com"
WP_ADMIN_PASS="password"

# Readiness poll tunables (~5 minute ceiling by default, consistent with the
# performance suite's 600s timeout).
READINESS_ATTEMPTS="${BENCHMARK_READINESS_ATTEMPTS:-60}"
READINESS_INTERVAL="${BENCHMARK_READINESS_INTERVAL:-5}"
DB_READINESS_ATTEMPTS="${BENCHMARK_DB_ATTEMPTS:-60}"
DB_READINESS_INTERVAL="${BENCHMARK_DB_INTERVAL:-5}"

# Option flags (defaults) and lifecycle state.
keep_up=false
clean_volumes=false
no_up=false
# Tracks whether THIS invocation is responsible for the stack, so teardown never
# removes a stack we did not bring up (guards against double-teardown).
stack_is_up=false

# ---------------------------------------------------------------------------
# compose — thin wrapper pinning the benchmark project name and compose file to
# every docker compose call, so this stack can never collide with the dev
# environment (docker-compose.yml, project default, port 8889).
# ---------------------------------------------------------------------------
compose() {
	docker compose -p "${COMPOSE_PROJECT}" -f "${COMPOSE_FILE}" "$@"
}

# ---------------------------------------------------------------------------
# wp_cli — run a WP-CLI command inside the "cli" service, always targeting the
# WordPress source root (--path) and passing --allow-root. "docker compose exec"
# runs as the container's root user (unlike the image entrypoint, which drops
# privileges), and WP-CLI refuses to run as root without this flag; this mirrors
# the repository's own containerised WP-CLI usage (tools/local-env "env:cli").
# ---------------------------------------------------------------------------
wp_cli() {
	compose exec -T cli wp "$@" --path="${WP_PATH}" --allow-root
}

# ---------------------------------------------------------------------------
# usage — print help text to stdout.
# ---------------------------------------------------------------------------
usage() {
	printf '%s\n' \
		'Usage: bash benchmarks/run-optimized.sh [options]' \
		'' \
		'Run the OPTIMIZED performance measurement: bring up the' \
		'isolated Docker benchmark stack (docker-compose.benchmark.yml, project' \
		'"blitzy-wp-benchmark", port 8890), ensure WordPress is installed, drive the' \
		'Playwright performance suite via benchmarks/run-benchmark.js, and write the raw' \
		'results to benchmarks/results/performance-results.json.' \
		'' \
		'Options:' \
		'  --keep-up         Leave the benchmark stack running after the run.' \
		'  --no-up           Assume the stack is already running; skip bring-up (and,' \
		'                    since this invocation does not own it, skip teardown).' \
		'  --clean-volumes   On teardown, also remove the isolated benchmark DB volume' \
		'                    (mysql-benchmark); the dev "mysql" volume is never touched.' \
		'  -h, --help        Print this help and exit 0 without touching Docker.' \
		'' \
		'Environment overrides (all optional):' \
		'  BENCHMARK_MYSQL_ROOT_PASSWORD  DB root password (default: password).' \
		'  BENCHMARK_READINESS_ATTEMPTS   HTTP readiness poll attempts (default: 60).' \
		'  BENCHMARK_READINESS_INTERVAL   Seconds between poll attempts (default: 5).' \
		'  BENCHMARK_DB_ATTEMPTS          Database readiness poll attempts (default: 60).' \
		'  BENCHMARK_DB_INTERVAL          Seconds between DB poll attempts (default: 5).' \
		'  TEST_RUNS                      Per-suite Playwright iterations (default: 20).' \
		'  BENCHMARK_RUNS                 Whole-suite invocation count (default: 1).' \
		'' \
		'This is a TEST/DEVELOPMENT-ONLY tool; never part of a production deployment.'
}

# ---------------------------------------------------------------------------
# require_command — fail early with a clear message if a needed binary is absent.
# ---------------------------------------------------------------------------
require_command() {
	if ! command -v "$1" >/dev/null 2>&1; then
		printf '[run-optimized] error: required command "%s" was not found in PATH.\n' "$1" >&2
		exit 1
	fi
}

# ---------------------------------------------------------------------------
# cleanup — EXIT trap. Tears the benchmark stack down deterministically on both
# success and failure, unless --keep-up was given or this invocation did not own
# the stack (--no-up / early exit). Preserves the triggering exit code.
# ---------------------------------------------------------------------------
cleanup() {
	local exit_code=$?

	# Restore any pre-existing src/wp-config.php we displaced for the benchmark
	# (see ensure_wordpress). Runs on the host regardless of stack ownership, so
	# the developer's original config is always put back.
	if [[ -n "${HOST_WP_CONFIG_BACKUP}" && -f "${HOST_WP_CONFIG_BACKUP}" ]]; then
		cp -f "${HOST_WP_CONFIG_BACKUP}" "${HOST_WP_CONFIG}"
		rm -f "${HOST_WP_CONFIG_BACKUP}"
		printf '[run-optimized] Restored the original src/wp-config.php.\n'
	fi

	if [[ "${keep_up}" == true ]]; then
		local down_hint="docker compose -p ${COMPOSE_PROJECT} -f ${COMPOSE_FILE} down"
		if [[ "${clean_volumes}" == true ]]; then
			down_hint="${down_hint} -v"
		fi
		printf '\n[run-optimized] --keep-up set; leaving the benchmark stack running.\n'
		printf '[run-optimized] Tear it down later with: %s\n' "${down_hint}"
	elif [[ "${stack_is_up}" != true ]]; then
		# Nothing we own to tear down (e.g. --no-up, --help, or an early failure
		# before bring-up). Leave any externally-managed stack alone.
		:
	elif [[ "${clean_volumes}" == true ]]; then
		printf '\n[run-optimized] Tearing down the benchmark stack and removing its volume...\n'
		compose down -v || true
	else
		printf '\n[run-optimized] Tearing down the benchmark stack...\n'
		compose down || true
	fi

	exit "${exit_code}"
}

# ---------------------------------------------------------------------------
# ensure_assets_built — guarantee the served source tree has its built JS/CSS.
# The nginx docroot is "src"; WordPress refuses to render an unbuilt source tree
# (HTTP 500, "running WordPress without JavaScript and CSS files"). A production
# `grunt build` runs `clean:js`, emptying src/**/js — the exact state that 500s
# here. This guarded, revision-appropriate safety net builds the DEV assets for
# WHATEVER revision is currently checked out only when they are absent; both the
# readable .js and the minified .min.js land under src/ (which is gitignored, so
# this never dirties the tracked tree). An already-built tree is a no-op, so the
# common "operator already built" path is unaffected.
# ---------------------------------------------------------------------------
ensure_assets_built() {
	if [[ -f "${repo_root}/src/wp-includes/js/wp-emoji-loader.js" ]]; then
		printf '[run-optimized] Built src/ assets present; skipping asset build.\n'
		return 0
	fi
	printf '[run-optimized] src/ assets missing; building dev assets (this may take a few minutes)...\n'
	( cd "${repo_root}" && CI=true npm run build:dev )
}

# ---------------------------------------------------------------------------
# bring_up — start the isolated benchmark stack. Prefer "up -d --wait" (blocks
# until healthchecks pass); fall back to a plain "up -d" if --wait is
# unsupported by the installed compose version (the explicit readiness poll
# below still guarantees the server is actually serving HTTP).
# ---------------------------------------------------------------------------
bring_up() {
	printf '[run-optimized] Bringing up the benchmark stack (project "%s", port %s)...\n' "${COMPOSE_PROJECT}" "${BENCH_PORT}"
	if ! compose up -d --wait; then
		printf '[run-optimized] "up -d --wait" was unavailable or failed; retrying with a plain "up -d".\n' >&2
		compose up -d
	fi
}

# ---------------------------------------------------------------------------
# wait_for_http — poll the fixed benchmark URL until the (now-installed) site
# serves a success response, so we never start measuring — or let Playwright
# decide the server is absent — before the stack is genuinely ready. Runs after
# ensure_wordpress, because a not-yet-installed site does not return success.
# ---------------------------------------------------------------------------
wait_for_http() {
	local attempt=1
	printf '[run-optimized] Waiting for %s to respond...\n' "${BENCH_URL}"
	while [[ "${attempt}" -le "${READINESS_ATTEMPTS}" ]]; do
		if curl -fsS -o /dev/null "${BENCH_URL}"; then
			printf '[run-optimized] Benchmark server is responding (attempt %d/%d).\n' "${attempt}" "${READINESS_ATTEMPTS}"
			return 0
		fi
		printf '[run-optimized] ...not ready yet (attempt %d/%d); retrying in %ss.\n' "${attempt}" "${READINESS_ATTEMPTS}" "${READINESS_INTERVAL}"
		sleep "${READINESS_INTERVAL}"
		attempt=$(( attempt + 1 ))
	done
	printf '[run-optimized] error: %s did not become ready after %d attempts.\n' "${BENCH_URL}" "${READINESS_ATTEMPTS}" >&2
	return 1
}

# ---------------------------------------------------------------------------
# wait_for_db — poll the database until it accepts an authenticated connection,
# using PHP's mysqli driver inside the cli container (the same driver the
# WordPress runtime uses). Two reasons drive this design:
#   * The mysql healthcheck ("mysqladmin ping") reports healthy during MySQL's
#     init window, before remote authenticated connections succeed, so
#     provisioning can otherwise race ahead of a truly ready database.
#   * We deliberately avoid "wp db query" here: it shells out to the external
#     mysql client binary, which against MySQL 8.4's default TLS (an
#     auto-generated self-signed certificate) fails with a TLS verification
#     error, whereas PHP's mysqlnd authenticates over TCP (caching_sha2_password
#     via the server's public key) exactly as WordPress does at runtime.
# Credentials are passed as container environment variables so the password is
# never interpolated into the probe command string.
# ---------------------------------------------------------------------------
wait_for_db() {
	local attempt=1
	printf '[run-optimized] Waiting for the database to accept connections...\n'
	while [[ "${attempt}" -le "${DB_READINESS_ATTEMPTS}" ]]; do
		if compose exec -T \
			-e WP_BENCH_DB_HOST="${DB_HOST}" \
			-e WP_BENCH_DB_USER="${DB_USER}" \
			-e WP_BENCH_DB_PASS="${DB_PASS}" \
			-e WP_BENCH_DB_NAME="${DB_NAME}" \
			cli php -r 'exit(@mysqli_connect(getenv("WP_BENCH_DB_HOST"), getenv("WP_BENCH_DB_USER"), getenv("WP_BENCH_DB_PASS"), getenv("WP_BENCH_DB_NAME")) ? 0 : 1);' >/dev/null 2>&1; then
			printf '[run-optimized] Database is ready (attempt %d/%d).\n' "${attempt}" "${DB_READINESS_ATTEMPTS}"
			return 0
		fi
		printf '[run-optimized] ...database not ready yet (attempt %d/%d); retrying in %ss.\n' "${attempt}" "${DB_READINESS_ATTEMPTS}" "${DB_READINESS_INTERVAL}"
		sleep "${DB_READINESS_INTERVAL}"
		attempt=$(( attempt + 1 ))
	done
	printf '[run-optimized] error: database did not become ready after %d attempts.\n' "${DB_READINESS_ATTEMPTS}" >&2
	return 1
}

# ---------------------------------------------------------------------------
# ensure_wordpress — idempotently provision WordPress inside the "cli" service.
# First guarantee wp-config.php exists, then guarantee the site is installed
# (the perf suite connects to the DB and authenticates over REST, so a working
# config + install are prerequisites). This runs BEFORE the HTTP readiness poll
# because provisioning only needs the database (reached through the cli
# container), not the web server, and a not-yet-installed site does not serve a
# success response. Both steps are guarded so re-running the script is safe; the
# "if ... / else" form keeps the non-zero probe from tripping "set -e".
# ---------------------------------------------------------------------------
ensure_wordpress() {
	# Authoritatively (re)write the benchmark config at the docroot-resolved path
	# (src/wp-config.php). A developer checkout typically already has one pointing
	# at the DEV database (host 127.0.0.1:3306), unreachable from the benchmark
	# network, which would make the served site fail with a "database error" 500;
	# so we do not "skip when present". Any pre-existing file is preserved first
	# and restored by cleanup(). src/wp-config.php is gitignored (never committed).
	if [[ -f "${HOST_WP_CONFIG}" ]]; then
		HOST_WP_CONFIG_BACKUP="$( mktemp )"
		cp -f "${HOST_WP_CONFIG}" "${HOST_WP_CONFIG_BACKUP}"
		printf '[run-optimized] Preserved existing src/wp-config.php (restored on teardown).\n'
	fi
	printf '[run-optimized] Writing benchmark wp-config.php (db host "%s", db "%s")...\n' "${DB_HOST}" "${DB_NAME}"
	wp_cli config create \
		--config-file="${WP_CONFIG_PATH_IN_CONTAINER}" \
		--dbname="${DB_NAME}" \
		--dbuser="${DB_USER}" \
		--dbpass="${DB_PASS}" \
		--dbhost="${DB_HOST}" \
		--skip-check \
		--force

	# is-installed and core install both connect to the database, so wait for
	# it to accept authenticated connections before running either.
	wait_for_db

	if wp_cli core is-installed >/dev/null 2>&1; then
		printf '[run-optimized] WordPress already installed; skipping install.\n'
	else
		printf '[run-optimized] Installing WordPress for the benchmark run...\n'
		wp_cli core install \
			--url="${BENCH_URL}" \
			--title="${WP_TITLE}" \
			--admin_user="${WP_ADMIN_USER}" \
			--admin_email="${WP_ADMIN_EMAIL}" \
			--admin_password="${WP_ADMIN_PASS}" \
			--skip-email
	fi

	# The single-post performance spec measures /2018/11/03/block-image/. Ensure
	# pretty permalinks and that exact fixture exist so the spec measures a real
	# published post rather than a 404 (theme-unit-test data is not imported by the
	# suite's global setup). Both steps are idempotent, so re-running is safe.
	wp_cli rewrite structure '/%year%/%monthnum%/%day%/%postname%/' >/dev/null 2>&1 || true
	if wp_cli post list --name=block-image --post_type=post --field=ID 2>/dev/null | grep -q .; then
		printf '[run-optimized] Single-post benchmark fixture already present; skipping.\n'
	else
		printf '[run-optimized] Creating single-post benchmark fixture (/2018/11/03/block-image/)...\n'
		wp_cli post create \
			--post_type=post \
			--post_status=publish \
			--post_title='Block Image' \
			--post_name='block-image' \
			--post_date='2018-11-03 10:00:00' \
			--post_content='<!-- wp:paragraph --><p>Benchmark single-post fixture.</p><!-- /wp:paragraph -->' \
			--porcelain >/dev/null
	fi
}

# ---------------------------------------------------------------------------
# install_mu_plugins — copy the test-only Server-Timing and cache-clearing
# must-use plugins into the runtime WordPress tree (src/wp-content/mu-plugins,
# mounted into the container at /var/www/src/wp-content/mu-plugins) so the
# measured requests actually emit the seven Server-Timing metrics (Rule 1
# observability) and perform the deterministic per-iteration object-cache reset.
# Without this step the runtime mu-plugins directory is empty and the metrics
# are silently absent from the run (the failure this wiring closes). The runtime
# mu-plugins directory is gitignored, so the copy never dirties the tracked
# tree; it is idempotent (re-running overwrites with identical bytes) and treats
# the committed sources as read-only. "Off" remains the physical absence of
# these files in any tree that has not run the benchmark.
# ---------------------------------------------------------------------------
install_mu_plugins() {
	local src_dir="${repo_root}/tests/performance/wp-content/mu-plugins"
	local dest_dir="${repo_root}/src/wp-content/mu-plugins"
	local plugin

	mkdir -p "${dest_dir}"
	for plugin in server-timing.php clear-cache.php; do
		if [[ ! -f "${src_dir}/${plugin}" ]]; then
			printf '[run-optimized] ERROR: required mu-plugin not found: %s\n' \
				"${src_dir}/${plugin}" >&2
			exit 1
		fi
		cp -f "${src_dir}/${plugin}" "${dest_dir}/${plugin}"
		printf '[run-optimized] Installed mu-plugin: %s\n' "${plugin}"
	done
}

# ---------------------------------------------------------------------------
# run_measurement — export the measurement environment and invoke the Node
# orchestrator. The unchanged tests/performance/playwright.config.js derives its
# base URL and web-server port from WP_BASE_URL, and inherits
# "reuseExistingServer: true" from the @wordpress/scripts base config; because
# the benchmark stack is ALREADY serving on this URL (guaranteed by wait_for_http
# above), Playwright reuses it instead of running its "npm run env:start"
# command (which would start the DEV environment). WP_ARTIFACTS_PATH points the
# reporter and the orchestrator at benchmarks/results. TEST_RUNS / BENCHMARK_RUNS
# are intentionally left untouched so the caller's values (or the defaults) apply.
# ---------------------------------------------------------------------------
run_measurement() {
	export WP_ARTIFACTS_PATH="${ARTIFACTS_DIR}"
	export WP_BASE_URL="${BENCH_URL}"
	printf '[run-optimized] Running the performance suite against %s (label: %s)...\n' "${BENCH_URL}" "${LABEL}"
	node benchmarks/run-benchmark.js --label "${LABEL}" --output "${OUTPUT_FILE}"
}

# ---------------------------------------------------------------------------
# print_summary — report where the optimized artifact landed and the next steps.
# ---------------------------------------------------------------------------
print_summary() {
	printf '\n[run-optimized] Optimized measurement complete.\n'
	printf '[run-optimized] Raw results written to: %s\n' "${OUTPUT_FILE}"
	printf '[run-optimized] Next steps:\n'
	printf '[run-optimized]   1. Diff this run against the baseline with:      node benchmarks/generate-diff-report.js\n'
	printf '[run-optimized]      (the baseline run must already have produced before-performance-results.json).\n'
}

# ---------------------------------------------------------------------------
# Option parsing. Handled before installing the EXIT trap so that --help and
# invalid-option paths exit without touching Docker.
# ---------------------------------------------------------------------------
while [[ $# -gt 0 ]]; do
	case "$1" in
		--keep-up)
			keep_up=true
			;;
		--clean-volumes)
			clean_volumes=true
			;;
		--no-up)
			no_up=true
			;;
		-h|--help)
			usage
			exit 0
			;;
		*)
			printf '[run-optimized] error: unknown option "%s".\n' "$1" >&2
			usage >&2
			exit 1
			;;
	esac
	shift
done

# From here on, Docker may be touched; ensure the stack is always cleaned up.
trap cleanup EXIT

require_command docker
require_command curl
require_command node

mkdir -p "${ARTIFACTS_DIR}"

# Guarantee the served source tree is built before nginx serves it (host-side;
# independent of Docker, so it also applies under --no-up).
ensure_assets_built

if [[ "${no_up}" == true ]]; then
	printf '[run-optimized] --no-up set; assuming the benchmark stack is already running.\n'
else
	# Claim ownership before bring-up so a partial failure is still torn down.
	stack_is_up=true
	bring_up
fi

# Provision WordPress first (it needs only the database, reached via the cli
# container), then wait for the installed site to serve, then measure.
ensure_wordpress
wait_for_http
install_mu_plugins
run_measurement
print_summary
