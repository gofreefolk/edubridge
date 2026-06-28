#!/usr/bin/env bash
# One-time GitHub Actions self-hosted runner install for EduBridge deploy.
#
# 1. Open: https://github.com/gofreefolk/edubridge/settings/actions/runners/new
# 2. Copy the registration token (valid ~1 hour)
# 3. Run:
#      REGISTRATION_TOKEN=XXXX RUNNER_DIR=/opt/github-runner-edubridge \
#        bash scripts/install-github-runner.sh
#
set -euo pipefail

REGISTRATION_TOKEN="${REGISTRATION_TOKEN:-}"
RUNNER_DIR="${RUNNER_DIR:-/opt/github-runner-edubridge}"
RUNNER_NAME="${RUNNER_NAME:-apstrix}"
RUNNER_LABELS="${RUNNER_LABELS:-self-hosted,linux,edubridge}"

if [[ -z "$REGISTRATION_TOKEN" ]]; then
  echo "REGISTRATION_TOKEN is required." >&2
  echo "Create one at: https://github.com/gofreefolk/edubridge/settings/actions/runners/new" >&2
  exit 1
fi

if [[ "$(uname -s)" != "Linux" ]]; then
  echo "This script is for Linux servers only." >&2
  exit 1
fi

ARCH="$(uname -m)"
case "$ARCH" in
  x86_64) RUNNER_ARCH="x64" ;;
  aarch64|arm64) RUNNER_ARCH="arm64" ;;
  *)
    echo "Unsupported architecture: $ARCH" >&2
    exit 1
    ;;
esac

RUNNER_VERSION="${RUNNER_VERSION:-$(curl -fsSL -o /dev/null -w '%{url_effective}' https://github.com/actions/runner/releases/latest | sed 's|.*/v||')}"
RUNNER_TARBALL="actions-runner-linux-${RUNNER_ARCH}-${RUNNER_VERSION}.tar.gz"
RUNNER_URL="https://github.com/actions/runner/releases/download/v${RUNNER_VERSION}/${RUNNER_TARBALL}"

mkdir -p "$RUNNER_DIR"
cd "$RUNNER_DIR"

if [[ ! -f ./config.sh ]]; then
  echo "Downloading runner v${RUNNER_VERSION} (${RUNNER_ARCH})..."
  curl -fsSL -o "$RUNNER_TARBALL" "$RUNNER_URL"
  tar xzf "$RUNNER_TARBALL"
  rm -f "$RUNNER_TARBALL"
fi

./config.sh \
  --url "https://github.com/gofreefolk/edubridge" \
  --token "$REGISTRATION_TOKEN" \
  --name "$RUNNER_NAME" \
  --labels "$RUNNER_LABELS" \
  --unattended \
  --replace

echo ""
echo "Runner configured. Install and start as a service:"
echo "  cd $RUNNER_DIR"
echo "  sudo ./svc.sh install"
echo "  sudo ./svc.sh start"
echo "  sudo ./svc.sh status"
echo ""
echo "Ensure PHP 8.5+ CLI is available for deploy-post.sh (php -v)."
