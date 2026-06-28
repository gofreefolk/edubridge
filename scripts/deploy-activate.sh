#!/usr/bin/env bash
# Activate a packaged release on the server (used by self-hosted GitHub runner).
#
# Required env:
#   APP_DIR          e.g. /www/wwwroot/edubridge
#   RELEASE_ID       e.g. git commit SHA
#   RELEASE_ARCHIVE    path to release.tar.gz (default: release.tar.gz)
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

mkdir -p "$RELEASES_DIR" "$SHARED_DIR/storage/app/public" "$SHARED_DIR/storage/logs"
mkdir -p "$SHARED_DIR/storage/framework/cache" "$SHARED_DIR/storage/framework/sessions" "$SHARED_DIR/storage/framework/views"

mkdir -p "$RELEASE_DIR"
tar -xzf "$RELEASE_ARCHIVE" -C "$RELEASE_DIR"

ln -sfn "$SHARED_DIR/.env" "$RELEASE_DIR/.env"
ln -sfn "$SHARED_DIR/storage" "$RELEASE_DIR/storage"

ln -sfn "$RELEASE_DIR" "$APP_DIR/current"
bash "$APP_DIR/current/scripts/deploy-post.sh" "$APP_DIR/current"

ls -1dt "$RELEASES_DIR"/* | tail -n +6 | xargs -r rm -rf

echo "Activated release $RELEASE_ID at $APP_DIR/current"
