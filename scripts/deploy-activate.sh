#!/usr/bin/env bash
# Activate a packaged release on the server (used by self-hosted GitHub runner).
#
# Required env:
#   APP_DIR          e.g. /www/wwwroot/edubridge
#   RELEASE_ID       e.g. git commit SHA
#   RELEASE_ARCHIVE  path to release.tar.gz (default: release.tar.gz)
#
# Order matters: the new release is fully prepared (migrations, caches) while the
# old release is still `current`, and the symlink only moves once everything
# succeeded. Any failure leaves the previous release live and the site up.
#
set -euo pipefail

APP_DIR="${APP_DIR:?APP_DIR is required}"
RELEASE_ID="${RELEASE_ID:?RELEASE_ID is required}"
RELEASE_ARCHIVE="${RELEASE_ARCHIVE:-release.tar.gz}"

if [[ ! -f "$RELEASE_ARCHIVE" ]]; then
  echo "Release archive not found: $RELEASE_ARCHIVE" >&2
  exit 1
fi

RELEASES_DIR="$APP_DIR/releases"
SHARED_DIR="$APP_DIR/shared"
RELEASE_DIR="$RELEASES_DIR/$RELEASE_ID"
CURRENT_LINK="$APP_DIR/current"

if [[ ! -f "$SHARED_DIR/.env" ]]; then
  echo "Missing $SHARED_DIR/.env — create it before deploying (see README)." >&2
  exit 1
fi

mkdir -p "$RELEASES_DIR" "$SHARED_DIR/storage/app/public" "$SHARED_DIR/storage/logs"
mkdir -p "$SHARED_DIR/storage/framework/cache" "$SHARED_DIR/storage/framework/sessions" "$SHARED_DIR/storage/framework/views"

rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR"
tar -xzf "$RELEASE_ARCHIVE" -C "$RELEASE_DIR"

ln -sfn "$SHARED_DIR/.env" "$RELEASE_DIR/.env"
rm -rf "$RELEASE_DIR/storage"
ln -sfn "$SHARED_DIR/storage" "$RELEASE_DIR/storage"

# Maintenance mode lives in the shared storage, so it must be lifted on any exit.
IN_MAINTENANCE=0
cleanup() {
  if [[ "$IN_MAINTENANCE" == "1" ]]; then
    php "$RELEASE_DIR/artisan" up || true
  fi
}
trap cleanup EXIT

# Put the old release into maintenance while the schema changes.
if [[ -e "$CURRENT_LINK/artisan" ]]; then
  php "$CURRENT_LINK/artisan" down --retry=60 || true
  IN_MAINTENANCE=1
fi

bash "$RELEASE_DIR/scripts/deploy-post.sh" "$RELEASE_DIR"

# Atomic switch: create the new link beside the old one, then rename over it.
ln -sfn "$RELEASE_DIR" "$APP_DIR/current.tmp"
mv -Tf "$APP_DIR/current.tmp" "$CURRENT_LINK"

php "$CURRENT_LINK/artisan" queue:restart || true
php "$CURRENT_LINK/artisan" up
IN_MAINTENANCE=0

# Keep the five newest releases (never the live one).
ls -1dt "$RELEASES_DIR"/* | tail -n +6 | grep -v "^$RELEASE_DIR\$" | xargs -r rm -rf

echo "Activated release $RELEASE_ID at $CURRENT_LINK"
