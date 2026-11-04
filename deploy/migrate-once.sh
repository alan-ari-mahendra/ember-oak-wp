#!/usr/bin/env bash
# ONE-TIME migration: local WordPress -> VPS. Run from the repo root on the local machine.
# Requires: server-setup.sh already run; SSH aliases `sukun-vps-brata` (root) and the deploy key.
set -euo pipefail
ROOT_HOST="${ROOT_HOST:-sukun-vps-brata}"
DEPLOY_HOST="${DEPLOY_HOST:-46.250.232.141}"
DEPLOY_KEY="${DEPLOY_KEY:-$HOME/.ssh/ember-oak-deploy}"
OLD_URL="${OLD_URL:-http://ember-oak.test}"
PROJECT="${PROJECT:-ember-oak}"
SITE_PORT="${SITE_PORT:-80}"
NEW_URL="${NEW_URL:-http://$DEPLOY_HOST$([ "$SITE_PORT" != 80 ] && echo ":$SITE_PORT")}"
REMOTE_ROOT="/var/www/$PROJECT"
PLUGIN_DIR="${PLUGIN_DIR:-ember-oak-core}"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
DSSH="ssh -i $DEPLOY_KEY -o IdentitiesOnly=yes -o BatchMode=yes"
# run a command as the deploy user on the server (script via stdin avoids nested-quote problems)
RW() { printf 'set -e; cd %s && %s\n' "$REMOTE_ROOT" "$*" | ssh -o BatchMode=yes "$ROOT_HOST" "runuser -u deploy -- bash -l -s"; }

WPV="$(wp core version 2>/dev/null | grep -Eo '^[0-9]+(\.[0-9]+)+' | tail -1)"
PREFIX="$(sed -nE "s/^\\\$table_prefix *= *'([A-Za-z0-9_]+)'.*/\1/p" wp-config.php | head -1)"
echo "local WP $WPV, table prefix $PREFIX"

echo "==> export local DB"
wp db export "$TMP/ember-oak.sql" --add-drop-table >/dev/null 2>&1
ls -lh "$TMP/ember-oak.sql"

echo "==> WordPress core $WPV on server"
RW "[ -f wp-load.php ] || wp core download --version=$WPV --skip-content --locale=en_US"

echo "==> rsync wp-content (plugins/themes/uploads; ember-oak-core is deployed by deploy.sh)"
rsync -az --no-owner --no-group --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
  --exclude='/cache' --exclude='/upgrade' --exclude='*.log' --exclude='debug.log' --exclude='/uploads/wc-logs' \
  --exclude="/plugins/$PLUGIN_DIR" --exclude='/plugins/index.php' \
  -e "$DSSH" wp-content/ "deploy@$DEPLOY_HOST:$REMOTE_ROOT/wp-content/"
$DSSH "deploy@$DEPLOY_HOST" "chmod -R g+w $REMOTE_ROOT/wp-content/uploads; mkdir -p $REMOTE_ROOT/wp-content/cache"

echo "==> wp-config.php (DB credentials stay on the server)"
ssh -o BatchMode=yes "$ROOT_HOST" "set -e; . /root/$PROJECT-db.env; cd $REMOTE_ROOT;
  [ -f wp-config.php ] || runuser -u deploy -- wp config create --dbname=\"\$DB_NAME\" --dbuser=\"\$DB_USER\" --dbpass=\"\$DB_PASS\" --dbprefix='$PREFIX' --dbcharset=utf8mb4 --skip-check --extra-php <<'PHP'
define( 'WP_HOME', '$NEW_URL' );
define( 'WP_SITEURL', '$NEW_URL' );
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISABLE_WP_CRON', true );
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'FS_METHOD', 'direct' );
define( 'WP_MEMORY_LIMIT', '256M' );
PHP
  chmod 640 wp-config.php; chown deploy:www-data wp-config.php"

echo "==> import DB"
rsync -az -e "$DSSH" "$TMP/ember-oak.sql" "deploy@$DEPLOY_HOST:/tmp/ember-oak.sql"
RW "wp db import /tmp/ember-oak.sql && rm -f /tmp/ember-oak.sql"

echo "==> URL rewrite $OLD_URL -> $NEW_URL"
RW "wp search-replace '$OLD_URL' '$NEW_URL' --all-tables --skip-columns=guid --report-changed-only"
RW "wp search-replace 'http:\\/\\/ember-oak.test' '$(printf '%s' "$NEW_URL" | sed 's#/#\\/#g')' --all-tables --skip-columns=guid --report-changed-only"

echo "==> first deploy of ember-oak-core"
DEPLOY_HOST="$DEPLOY_HOST" DEPLOY_USER=deploy REMOTE_ROOT="$REMOTE_ROOT" PROJECT="$PROJECT" PLUGIN_DIR="$PLUGIN_DIR" SSH_OPTS="-i $DEPLOY_KEY -o IdentitiesOnly=yes" SITE_URL="$NEW_URL" ./deploy/deploy.sh || true

echo "==> finalize (permalinks, Elementor, Theme Builder cache, no-index until domain)"
RW "wp option update blog_public 0; wp rewrite structure '/%postname%/'; wp rewrite flush; wp elementor flush-css; wp cache flush"
RW "wp eval '\\ElementorPro\\Modules\\ThemeBuilder\\Module::instance()->get_conditions_manager()->get_cache()->regenerate(); echo \"conditions regenerated\\n\";'"
echo "MIGRATION_DONE -> $NEW_URL  (change the admin password!)"
