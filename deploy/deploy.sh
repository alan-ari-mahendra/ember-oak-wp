#!/usr/bin/env bash
# Deploy wp-content/plugins/ember-oak-core to the server. Used by GitHub Actions and for manual deploys.
#   DEPLOY_HOST=46.250.232.141 DEPLOY_USER=deploy SSH_OPTS="-i ~/.ssh/ember-oak-deploy" ./deploy/deploy.sh
# Backs up the current plugin, rsyncs, flushes caches, runs a health check, and rolls back on failure.
set -euo pipefail
HOST="${DEPLOY_HOST:?DEPLOY_HOST required}"
DUSER="${DEPLOY_USER:-deploy}"
SITE_URL="${SITE_URL:-http://$HOST}"
REMOTE_ROOT="${REMOTE_ROOT:-/var/www/ember-oak}"       # per project, e.g. /var/www/my-other-site
SITE_PORT="${SITE_PORT:-}"                              # optional; defaults to the port in SITE_URL, else 80/443
PLUGIN_DIR="${PLUGIN_DIR:-ember-oak-core}"              # plugin folder to deploy for this project
PROJECT="${PROJECT:-$(basename "$REMOTE_ROOT")}"
SSH_OPTS="${SSH_OPTS:-}"
HOSTPORT="$(printf '%s' "$SITE_URL" | sed -E 's#^https?://##; s#/.*$##')"
SITE_HOST="${HOSTPORT%%:*}"                             # Host header sent to nginx (nginx ignores the port when matching server_name)
if [ -z "$SITE_PORT" ]; then
  case "$HOSTPORT" in *:*) SITE_PORT="${HOSTPORT##*:}";; *) SITE_PORT=$([[ "$SITE_URL" == https://* ]] && echo 443 || echo 80);; esac
fi
[[ "$SITE_PORT" =~ ^[0-9]+$ ]] || { echo "bad SITE_PORT: $SITE_PORT"; exit 1; }
# health checks run on the server against its local nginx; TLS is terminated there once a domain/cert exists
SCHEME=http; [ "$SITE_PORT" = "443" ] && SCHEME=https

SRC="wp-content/plugins/$PLUGIN_DIR/"
PLUGINS="$REMOTE_ROOT/wp-content/plugins"
DEST="$PLUGINS/$PLUGIN_DIR"
SHA="${GITHUB_SHA:-$(git rev-parse HEAD 2>/dev/null || echo manual)}"
BK="/var/backups/$PROJECT/$PLUGIN_DIR-$(date +%Y%m%d%H%M%S)-${SHA:0:7}.tgz"
R() { ssh $SSH_OPTS -o BatchMode=yes "$DUSER@$HOST" "$@"; }

[ -d "$SRC" ] || { echo "run from repo root ($SRC missing)"; exit 1; }

echo "==> backup current plugin"
R "mkdir -p '/var/backups/$PROJECT' '$DEST' && tar -czf '$BK' -C '$PLUGINS' '$PLUGIN_DIR' && echo saved $BK"

echo "==> rsync"
rsync -az --delete --no-owner --no-group --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
  --exclude='.DS_Store' --exclude='*.log' -e "ssh $SSH_OPTS -o BatchMode=yes" "$SRC" "$DUSER@$HOST:$DEST/"

post_deploy() {
  R "cd '$REMOTE_ROOT' && wp cache flush >/dev/null 2>&1 || true; wp elementor flush-css >/dev/null 2>&1 || true; sudo -n /usr/bin/systemctl reload php8.3-fpm"
}
health() {
  # checked on the server itself so it does not depend on runner->VPS networking
  local base="$SCHEME://127.0.0.1:$SITE_PORT"; local k=""; [ "$SCHEME" = https ] && k="-k"
  R "set -e; H=\$(curl -fsS $k -m 25 -H 'Host: $SITE_HOST' $base/); echo \"\$H\" | grep -q '${HEALTH_MARKER:-eo-header}'; \
     curl -fsS $k -m 25 -o /dev/null -H 'Host: $SITE_HOST' $base/wp-json/; \
     curl -fsS $k -m 25 -o /dev/null -H 'Host: $SITE_HOST' '$base${HEALTH_PATH:-/product/guji-natural/}'"
}

echo "==> post-deploy"; post_deploy
echo "==> health check"
if health; then
  echo "HEALTH_OK"
  R "ls -1t /var/backups/$PROJECT/$PLUGIN_DIR-*.tgz 2>/dev/null | tail -n +6 | xargs -r rm -f"
else
  echo "HEALTH_FAILED -> rolling back to $BK" >&2
  R "rm -rf '$DEST' && tar -xzf '$BK' -C '$PLUGINS'"
  post_deploy || true
  exit 1
fi
