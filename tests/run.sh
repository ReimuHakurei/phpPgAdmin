#!/usr/bin/env bash
# Start phpPgAdmin + PostgreSQL in Docker, crawl the UI, check the PHP log.
#
#   tests/run.sh                         # PHP 8.5, PostgreSQL 18
#   PG_VERSION=12 PHP_VERSION=8.1 tests/run.sh
#   KEEP=1 tests/run.sh                  # leave containers running afterwards
#   BROWSER=1 tests/run.sh               # also run the headless Chrome checks
#
# Set PPA_IMAGE to test an image you already built instead of building one.

set -euo pipefail

cd "$(dirname "$0")"

export PG_VERSION="${PG_VERSION:-18}"
export PHP_VERSION="${PHP_VERSION:-8.5}"
export PPA_PORT="${PPA_PORT:-8080}"

compose() { docker compose -f compose.yaml "$@"; }

cleanup() {
	if [ "${KEEP:-0}" != 1 ]; then
		compose down -v --remove-orphans >/dev/null 2>&1 || true
	fi
}
trap cleanup EXIT

echo "== PHP $PHP_VERSION, PostgreSQL $PG_VERSION"

if [ -n "${PPA_IMAGE:-}" ]; then
	compose up -d --wait --no-build
else
	compose up -d --wait --build
fi

status=0
python3 crawl.py "http://127.0.0.1:$PPA_PORT" "db:5432:allow" ppa ppa || status=1
python3 dblogin.py "http://127.0.0.1:$PPA_PORT" scoped scoped || status=1

# Headless Chrome checks of the JavaScript side (needs Node and Chrome; run
# "npm ci --prefix tests/browser" once). CHROME points at the browser binary.
if [ "${BROWSER:-0}" = 1 ]; then
	for t in shell.mjs editor.js grid.js dialog.mjs; do
		echo "== browser/$t"
		node "browser/$t" "http://127.0.0.1:$PPA_PORT/" || status=1
	done
fi

# Anything PHP complained about ends up in the Apache error log (container stderr).
compose logs --no-log-prefix app 2>&1 \
	| grep -E "PHP (Fatal error|Parse error|Warning|Notice|Deprecated)" \
	| sed -E 's/^.*\] (PHP )/\1/; s/\\nStack trace:.*//' \
	| sort | uniq -c | sort -rn > php-errors.log || true
if [ -s php-errors.log ]; then
	echo "PHP logged errors:"
	cat php-errors.log
	status=1
fi
rm -f php-errors.log

if [ $status -eq 0 ]; then
	echo "OK"
fi
exit $status
