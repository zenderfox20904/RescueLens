#!/bin/sh
# shellcheck disable=SC2016  # PHP code is meant to be single quoted
# Container start: prepare .env, wait for MySQL, seed the demo data once, then serve.
set -e
cd /app
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT}"
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Exception $e) { exit(1); }'; do sleep 2; done

php artisan migrate --force
USERS=$(php -r '$p = new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); echo $p->query("SELECT COUNT(*) FROM users")->fetchColumn();')
if [ "$USERS" = "0" ]; then
  echo "Seeding demo data"
  php artisan db:seed --force
  php artisan db:seed --class=JudgeDemoSeeder --force
  if [ -n "$META_MODEL_API_KEY$LLAMA_API_KEY" ]; then php artisan rescuelens:analyze-seeded || true; fi
fi
php artisan storage:link >/dev/null 2>&1 || true
php artisan config:clear >/dev/null
exec php artisan serve --host=0.0.0.0 --port=8000
