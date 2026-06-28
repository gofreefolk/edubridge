#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${1:-$(pwd)}"
cd "$APP_DIR"

php artisan down --retry=60 || true

php artisan migrate --force
php artisan storage:link --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true
php artisan queue:restart 2>/dev/null || true

php artisan up

echo "Deploy complete."
