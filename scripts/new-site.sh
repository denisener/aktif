#!/usr/bin/env bash
#
# Provision a new site clone from this base template.
#
# Usage:
#   scripts/new-site.sh <site-slug> <domain> [--with-demo]
#
# Example:
#   scripts/new-site.sh acme acme.example.com
#   scripts/new-site.sh acme acme.example.com --with-demo
#
# What it does:
#   1. git clone this base repo into ../sites/<site-slug> (sibling directory)
#   2. renames the clone's "origin" remote to "upstream", so core fixes
#      can be pulled later with: git fetch upstream && git merge upstream/master
#      (push the clone to its own git host under a new "origin" yourself —
#      this script does not create remote repos)
#   3. creates a MySQL database for the site
#   4. writes .env from .env.example with site-specific values filled in
#   5. composer install, php artisan key:generate
#   6. php artisan site:install (imports shop.sql, creates the admin user,
#      activates the full route set, applies branding)
#
# Configure via environment variables (all optional, shown with defaults):
#   BASE_REPO         path/URL to clone from      (default: this script's repo)
#   SITES_DIR         where site clones are created (default: ../sites next to base repo)
#   DB_HOST           MySQL host                 (default: 127.0.0.1)
#   DB_ROOT_USER      MySQL user for DB creation  (default: root)
#   DB_ROOT_PASSWORD  MySQL password              (default: empty)
#   MYSQL_BIN         mysql client binary         (default: mysql)
#   SITE_ADMIN_EMAIL  admin login                 (default: admin@<domain>)
#   SITE_ADMIN_PASSWORD  admin password           (default: randomly generated, printed once)
#   SITE_THEME        theme folder name           (default: classic)
#   LICENSE_CODE      LicenseBox license code for this site's activation
#                     (default: blank — site:install skips activation if unset)
#   LICENSE_CLIENT_NAME  LicenseBox activation identifier (default: the domain)
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

SITE_SLUG="${1:?Usage: scripts/new-site.sh <site-slug> <domain> [--with-demo]}"
DOMAIN="${2:?Usage: scripts/new-site.sh <site-slug> <domain> [--with-demo]}"
WITH_DEMO=""
if [ "${3:-}" = "--with-demo" ]; then
  WITH_DEMO="--with-demo"
fi

BASE_REPO="${BASE_REPO:-$SCRIPT_DIR}"
SITES_DIR="${SITES_DIR:-$(dirname "$SCRIPT_DIR")/sites}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_ROOT_USER="${DB_ROOT_USER:-root}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-}"
MYSQL_BIN="${MYSQL_BIN:-mysql}"

TARGET_DIR="$SITES_DIR/$SITE_SLUG"
DB_NAME="site_$(echo "$SITE_SLUG" | tr '-' '_')"
ADMIN_EMAIL="${SITE_ADMIN_EMAIL:-admin@$DOMAIN}"
ADMIN_PASSWORD="${SITE_ADMIN_PASSWORD:-$(openssl rand -base64 18 | tr -d '=+/')}"
THEME="${SITE_THEME:-classic}"
LICENSE_CODE="${LICENSE_CODE:-}"
LICENSE_CLIENT_NAME="${LICENSE_CLIENT_NAME:-$DOMAIN}"

if [ -e "$TARGET_DIR" ]; then
  echo "Error: $TARGET_DIR already exists." >&2
  exit 1
fi

echo "==> Cloning base template into $TARGET_DIR"
git clone "$BASE_REPO" "$TARGET_DIR"
cd "$TARGET_DIR"
git remote rename origin upstream
echo "    origin -> renamed to 'upstream'. Add this clone's own remote yourself: git remote add origin <site-repo-url>"

echo "==> Creating database $DB_NAME"
MYSQL_PWD="$DB_ROOT_PASSWORD" "$MYSQL_BIN" -h "$DB_HOST" -u "$DB_ROOT_USER" \
  -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4;"

echo "==> Writing .env"
cp .env.example .env
php_escape() { printf '%s' "$1" | sed -e 's/[\/&]/\\&/g'; }
sed -i \
  -e "s/^APP_URL=.*/APP_URL=\"https:\/\/$(php_escape "$DOMAIN")\"/" \
  -e "s/^DB_DATABASE=.*/DB_DATABASE=\"$(php_escape "$DB_NAME")\"/" \
  -e "s/^DB_HOST=.*/DB_HOST=\"$(php_escape "$DB_HOST")\"/" \
  -e "s/^SITE_THEME=.*/SITE_THEME=\"$(php_escape "$THEME")\"/" \
  -e "s/^SITE_NAME=.*/SITE_NAME=\"$(php_escape "$SITE_SLUG")\"/" \
  -e "s/^SITE_ADMIN_EMAIL=.*/SITE_ADMIN_EMAIL=\"$(php_escape "$ADMIN_EMAIL")\"/" \
  -e "s/^SITE_ADMIN_PASSWORD=.*/SITE_ADMIN_PASSWORD=\"$(php_escape "$ADMIN_PASSWORD")\"/" \
  -e "s/^LICENSE_CODE=.*/LICENSE_CODE=\"$(php_escape "$LICENSE_CODE")\"/" \
  -e "s/^LICENSE_CLIENT_NAME=.*/LICENSE_CLIENT_NAME=\"$(php_escape "$LICENSE_CLIENT_NAME")\"/" \
  .env

echo "==> composer install"
composer install --no-interaction --prefer-dist --no-progress

echo "==> php artisan key:generate"
php artisan key:generate --force

echo "==> php artisan site:install $WITH_DEMO"
php artisan site:install $WITH_DEMO

cat <<SUMMARY

==> Site '$SITE_SLUG' provisioned at $TARGET_DIR
    Admin login:    $ADMIN_EMAIL
    Admin password: $ADMIN_PASSWORD
    Database:       $DB_NAME
    Theme:          $THEME
    License:        $([ -n "$LICENSE_CODE" ] && echo "activated ($LICENSE_CLIENT_NAME)" || echo "not set — LICENSE_CODE was blank, site:install skipped activation")

    Reminders:
    - This app serves from the project root, not public/ (see repo notes) —
      point the webserver's document root at $TARGET_DIR.
    - Add a real git remote for this site: git remote add origin <url>
    - Pull core fixes later with: git fetch upstream && git merge upstream/master
SUMMARY
