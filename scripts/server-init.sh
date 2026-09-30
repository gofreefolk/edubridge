#!/usr/bin/env bash
# One-time production server setup. Run on the server as the deploy user.
#
# Each project on the server needs its own APP_DIR (does not touch other sites).
#
# Usage (aaPanel example):
#   export APP_DIR=/www/wwwroot/edubridge.yourdomain.com
#   bash scripts/server-init.sh
#
set -euo pipefail

APP_DIR="${APP_DIR:?Set APP_DIR to this project's root, e.g. /www/wwwroot/edubridge.yourdomain.com}"

mkdir -p "$APP_DIR"/{releases,shared/storage/{app/public,framework/{cache,sessions,views},logs}}

if [[ ! -f "$APP_DIR/shared/.env" ]]; then
  echo "Copy your production .env to: $APP_DIR/shared/.env"
  echo "Then run: cd $APP_DIR/current && php artisan key:generate"
fi

chmod -R ug+rwx "$APP_DIR/shared/storage"
chmod -R ug+rwx "$APP_DIR/shared/storage/framework"

echo "Server directories ready at $APP_DIR"
echo "Point your web server document root to: $APP_DIR/current/public"
