#!/usr/bin/env bash
# Read path load test with autocannon. Usage: ./loadtest/run.sh [base_url]
set -euo pipefail
BASE="${1:-http://127.0.0.1:8000/api/v1}"
for path in /health /incidents /incidents/map; do
  echo "== GET $path"
  npx --yes autocannon@8 -c 50 -d 15 --renderStatusCodes "$BASE$path"
done
