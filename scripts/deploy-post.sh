#!/usr/bin/env bash
# Prepare a release directory before it goes live: migrate and warm caches.
# Called by deploy-activate.sh with the NEW release path; it does not switch traffic.
set -euo pipefail

RELEASE_DIR="${1:-$(pwd)}"
cd "$RELEASE_DIR"

php artisan migrate --force
php artisan storage:link --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true

echo "Release prepared: $RELEASE_DIR"
