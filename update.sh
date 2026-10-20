#!/bin/bash
# Cloyne (Mac): pull the latest code, update the database, run checks, and start the app.
# Run from the Cloyne folder in Terminal:  ./update.sh

cd "$(dirname "$0")" || exit 1

fail() {
  echo
  echo "*** Something went wrong above. Take a screenshot of this window and send it to Claude. ***"
  exit 1
}

echo
echo "=== 1/5  Getting the latest code from GitHub ==="
git pull || fail

echo
echo "=== 2/5  Installing any new Laravel packages ==="
composer install --no-interaction || fail

echo
echo "=== 3/5  Updating the database ==="
if [ ! -f .env ]; then cp .env.example .env && php artisan key:generate; fi
[ -f database/database.sqlite ] || touch database/database.sqlite
php artisan migrate --force || fail

echo
echo "=== 4/5  Running checks ==="
php artisan test || fail

echo
echo "=== 5/5  Starting Cloyne ==="
# Background worker for AI audits; it stops when you close this window or press Ctrl+C.
php artisan queue:restart >/dev/null 2>&1
php artisan queue:work --tries=2 --timeout=150 > storage/logs/worker.log 2>&1 &
WORKER=$!
trap 'kill $WORKER 2>/dev/null' EXIT
echo "Open http://127.0.0.1:8000 in your browser. Press Ctrl+C here to stop Cloyne."
(sleep 2 && open http://127.0.0.1:8000) &
php artisan serve
