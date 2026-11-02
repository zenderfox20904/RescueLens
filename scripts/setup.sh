#!/usr/bin/env bash
# RescueLens local setup without Docker (macOS and Linux).
# Usage: ./scripts/setup.sh            install, create the database, seed demo data
#        ./scripts/setup.sh --run      same, then start the API (8000) and web (5173)
# Optional environment: DB_DATABASE DB_USERNAME DB_PASSWORD DB_HOST DB_PORT META_MODEL_API_KEY
#                       SKIP_INSTALL=1 to skip composer and npm installs
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DB_DATABASE="${DB_DATABASE:-rescuelens}"; DB_USERNAME="${DB_USERNAME:-root}"; DB_PASSWORD="${DB_PASSWORD:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"; DB_PORT="${DB_PORT:-3306}"; API_PORT="${API_PORT:-8000}"; WEB_PORT="${WEB_PORT:-5173}"

need() { command -v "$1" >/dev/null 2>&1 || { echo "Missing $1. See docs/SETUP.md"; exit 1; }; }
need php; need composer; need npm; need mysql

set_env() { # set_env FILE KEY VALUE (value never printed)
  local f="$1" k="$2" v="$3"
  if grep -q "^$k=" "$f"; then
    # shellcheck disable=SC2016
    php -r '$f=$argv[1];$k=$argv[2];$v=$argv[3];$s=file_get_contents($f);$s=preg_replace("/^".preg_quote($k,"/")."=.*$/m",$k."=".$v,$s);file_put_contents($f,$s);' "$f" "$k" "$v"
  else echo "$k=$v" >> "$f"; fi
}

echo "1/5 Backend environment"
cd "$ROOT/backend"
[ -f .env ] || cp .env.example .env
set_env .env DB_DATABASE "$DB_DATABASE"; set_env .env DB_USERNAME "$DB_USERNAME"; set_env .env DB_PASSWORD "$DB_PASSWORD"
set_env .env DB_HOST "$DB_HOST"; set_env .env DB_PORT "$DB_PORT"; set_env .env APP_URL "http://localhost:$API_PORT"
set_env .env CORS_ALLOWED_ORIGINS "http://localhost:$WEB_PORT,http://127.0.0.1:$WEB_PORT"
[ -n "${META_MODEL_API_KEY:-}" ] && set_env .env META_MODEL_API_KEY "$META_MODEL_API_KEY"
[ "${SKIP_INSTALL:-0}" = "1" ] || composer install --no-interaction
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

echo "2/5 Database $DB_DATABASE"
MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
  -e "CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --force

echo "3/5 Demo data"
USERS=$(MYSQL_PWD="$DB_PASSWORD" mysql -N -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" -e "SELECT COUNT(*) FROM users")
if [ "$USERS" = "0" ]; then
  php artisan db:seed --force
  php artisan db:seed --class=JudgeDemoSeeder --force
  if grep -qE '^(META_MODEL_API_KEY|LLAMA_API_KEY)=.+' .env; then php artisan rescuelens:analyze-seeded || true; fi
else
  echo "Users already exist, skipping seeding"
fi
php artisan storage:link >/dev/null 2>&1 || true

echo "4/5 Web"
cd "$ROOT/frontend"
[ -f .env ] || cp .env.example .env
set_env .env VITE_API_URL "http://127.0.0.1:$API_PORT/api/v1"; set_env .env VITE_SHARE_BASE "http://127.0.0.1:$API_PORT"
[ "${SKIP_INSTALL:-0}" = "1" ] || npm install --no-audit --no-fund

echo "5/5 Done"
echo "Web: http://localhost:$WEB_PORT   API: http://127.0.0.1:$API_PORT/api/v1"
echo "Demo logins: docs/DEMO_ACCESS.md (password RescueLensDemo2026!)"
if [ "${1:-}" = "--run" ]; then
  cd "$ROOT/backend" && php artisan serve --host=0.0.0.0 --port="$API_PORT" &
  API_PID=$!
  trap 'kill $API_PID 2>/dev/null' EXIT
  cd "$ROOT/frontend" && npm run dev -- --port "$WEB_PORT" --strictPort
else
  echo "Start: (cd backend && php artisan serve --port=$API_PORT) and (cd frontend && npm run dev)"
fi
