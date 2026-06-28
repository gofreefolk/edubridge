#!/usr/bin/env bash
# One-time production server setup. Run on the server as the deploy user.
#
# Usage:
#   export APP_DIR=/var/www/edubridge
#   bash server-init.sh
#
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/edubridge}"

mkdir -p "$APP_DIR"/{releases,shared/storage/{app/public,framework/{cache,sessions,views},logs}}

if [[ ! -f "$APP_DIR/shared/.env" ]]; then
  echo "Copy your production .env to: $APP_DIR/shared/.env"
  echo "Then run: cd $APP_DIR/current && php artisan key:generate"
fi

chmod -R ug+rwx "$APP_DIR/shared/storage"
chmod -R ug+rwx "$APP_DIR/shared/storage/framework"

echo "Server directories ready at $APP_DIR"
echo "Point your web server document root to: $APP_DIR/current/public"
