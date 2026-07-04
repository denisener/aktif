#!/usr/bin/env bash
#
# Package this base template into a distributable zip — for hosts where
# git/composer aren't an option (shared hosting via cPanel/FTP). Upload
# the zip, extract it, then either browse to /install to run the wizard,
# or run `php artisan site:install` over SSH if the host allows it.
#
# This is the non-git counterpart to scripts/new-site.sh (which clones
# via git for hosts/workflows that do have git+composer+CLI access).
#
# Usage:
#   scripts/build-zip.sh [output.zip] [--no-composer] [--no-demo]
#
#   --no-composer   Skip `composer install`; package vendor/ as-is (use
#                   this if you already built vendor/ elsewhere, e.g. on
#                   a machine running the target PHP version).
#   --no-demo       Don't bundle public/demo.sql + public/uploads.zip
#                   (the optional sample catalog).
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$SCRIPT_DIR"

OUTPUT="aktif-$(date +%Y%m%d).zip"
SKIP_COMPOSER=""
SKIP_DEMO=""
for arg in "$@"; do
  case "$arg" in
    --no-composer) SKIP_COMPOSER=1 ;;
    --no-demo) SKIP_DEMO=1 ;;
    *) OUTPUT="$arg" ;;
  esac
done
case "$OUTPUT" in
  /*) OUT_PATH="$OUTPUT" ;;
  *) OUT_PATH="$SCRIPT_DIR/$OUTPUT" ;;
esac

BUILD_DIR="$(mktemp -d)"
trap 'rm -rf "$BUILD_DIR"' EXIT
PKG_DIR="$BUILD_DIR/pkg"
mkdir -p "$PKG_DIR"

echo "==> Exporting git-tracked files (HEAD)..."
git archive HEAD | tar -x -C "$PKG_DIR"

if [ -z "$SKIP_DEMO" ]; then
  echo "==> Bundling optional demo catalog..."
  for f in public/demo.sql public/uploads.zip; do
    if [ -f "$SCRIPT_DIR/$f" ]; then
      mkdir -p "$PKG_DIR/$(dirname "$f")"
      cp "$SCRIPT_DIR/$f" "$PKG_DIR/$f"
    else
      echo "    (skipping $f, not found locally)"
    fi
  done
fi

if [ -z "$SKIP_COMPOSER" ]; then
  echo "==> Installing production dependencies (composer install --no-dev)..."
  if ! (cd "$PKG_DIR" && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist); then
    echo "" >&2
    echo "composer install failed inside the package — commonly a PHP version" >&2
    echo "mismatch between this machine and composer.lock (see project notes)." >&2
    echo "Fix on a machine with a compatible PHP, or build vendor/ separately" >&2
    echo "and re-run with --no-composer to just package what's there." >&2
    exit 1
  fi
else
  echo "==> --no-composer: copying existing vendor/ as-is..."
  if [ -d "$SCRIPT_DIR/vendor" ]; then
    cp -r "$SCRIPT_DIR/vendor" "$PKG_DIR/vendor"
  else
    echo "Warning: no local vendor/ found to copy; the package will have no vendor/ directory." >&2
  fi
fi

echo "==> Removing dev-only files not needed on a live site..."
rm -rf "$PKG_DIR/tests" "$PKG_DIR/scripts" "$PKG_DIR/.github" "$PKG_DIR/phpunit.xml"

echo "==> Creating $OUT_PATH"
rm -f "$OUT_PATH"
if command -v zip >/dev/null 2>&1; then
  (cd "$PKG_DIR" && zip -rq "$OUT_PATH" .)
elif command -v powershell.exe >/dev/null 2>&1; then
  WIN_PKG_DIR="$(cd "$PKG_DIR" && pwd -W 2>/dev/null || pwd)"
  WIN_OUT_PATH="$(cd "$(dirname "$OUT_PATH")" && pwd -W 2>/dev/null || dirname "$OUT_PATH")/$(basename "$OUT_PATH")"
  powershell.exe -NoProfile -Command "Compress-Archive -Path '${WIN_PKG_DIR}\\*' -DestinationPath '${WIN_OUT_PATH}' -Force"
else
  echo "Error: no 'zip' command and no PowerShell found to create the archive." >&2
  exit 1
fi

echo ""
echo "==> Built $OUT_PATH"
echo "    Upload + extract to shared hosting (document root = extracted folder,"
echo "    not extracted-folder/public — this app serves from its project root),"
echo "    then browse to /install to run the setup wizard, or run"
echo "    'php artisan site:install' over SSH if the host allows CLI access."
