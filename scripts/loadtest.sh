#!/usr/bin/env bash
# Read path load test with autocannon against a running API.
# Usage: ./scripts/loadtest.sh [base_url] [connections] [seconds]
set -euo pipefail
BASE="${1:-http://127.0.0.1:8001/api/v1}"; C="${2:-20}"; D="${3:-10}"
for path in /health "/incidents?per_page=10" "/incidents/map?hours=168"; do
  echo "== GET $path (c=$C, ${D}s)"
  npx --yes autocannon@8 -c "$C" -d "$D" --renderStatusCodes "$BASE$path"
done
