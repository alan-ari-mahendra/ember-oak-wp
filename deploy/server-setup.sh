#!/usr/bin/env bash
# Provision a WordPress project on the shared VPS. Idempotent; safe to run again.
# Run as root on the VPS (from the repo root, locally):
#   ssh root@HOST 'DEPLOY_PUBKEY="ssh-ed25519 AAAA..." PROJECT=ember-oak SITE_PORT=80 bash -s' < deploy/server-setup.sh
#
# Per project it creates: /var/www/$PROJECT, php-fpm pool + socket, MySQL db/user, nginx vhost on $SITE_PORT, WP-Cron entry.
# It never edits other projects' nginx/pool/db files, docker, or the default nginx site.
set -euo pipefail
: "${DEPLOY_PUBKEY:?set DEPLOY_PUBKEY}"
PROJECT="${PROJECT:-ember-oak}"
[[ "$PROJECT" =~ ^[a-z0-9-]+$ ]] || { echo "PROJECT must match [a-z0-9-]+"; exit 1; }
SITE_PORT="${SITE_PORT:-80}"
[[ "$SITE_PORT" =~ ^[0-9]+$ ]] && [ "$SITE_PORT" -ge 1 ] && [ "$SITE_PORT" -le 65535 ] || { echo "bad SITE_PORT"; exit 1; }
SERVER_NAME="${SERVER_NAME:-${SITE_IP:-46.250.232.141}}"   # IP or domain(s), space separated
OPEN_FIREWALL="${OPEN_FIREWALL:-1}"
ROOT="/var/www/$PROJECT"
DBN="${PROJECT//-/_}"
PHPV=8.3
SOCK="/run/php/$PROJECT.sock"
ENVF="/root/$PROJECT-db.env"

# --- port safety: refuse if something else already listens on this port
LISTENER="$(ss -tlnH "sport = :$SITE_PORT" | head -1 || true)"
if [ -n "$LISTENER" ] && ! ss -tlnpH "sport = :$SITE_PORT" | grep -q nginx; then
  echo "port $SITE_PORT is already used by a non-nginx process:"; ss -tlnpH "sport = :$SITE_PORT"; exit 1
fi
# other nginx vhost already claiming the same port + server_name?
if grep -rlE "listen\s+(\[::\]:)?$SITE_PORT\b" /etc/nginx/sites-enabled 2>/dev/null | grep -v "/$PROJECT\$" | xargs -r grep -lE "server_name\s+[^;]*\b${SERVER_NAME%% *}\b" 2>/dev/null | grep -q .; then
  echo "another vhost already serves ${SERVER_NAME%% *}:$SITE_PORT"; exit 1
fi

export DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=l   # l = list only; never auto-restart other services
if ! dpkg -s php${PHPV}-fpm >/dev/null 2>&1 || ! command -v wp >/dev/null; then
  apt-get update -qq
  apt-get install -y -qq php${PHPV}-fpm php${PHPV}-mysql php${PHPV}-curl php${PHPV}-gd php${PHPV}-imagick php${PHPV}-mbstring \
    php${PHPV}-xml php${PHPV}-zip php${PHPV}-intl php${PHPV}-bcmath php${PHPV}-soap php${PHPV}-opcache unzip less
fi
if ! command -v wp >/dev/null; then
  curl -fsSL -o /tmp/wp-cli.phar https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar
  curl -fsSL https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar.sha512 -o /tmp/wp-cli.sha512
  echo "$(tr -d '[:space:]' </tmp/wp-cli.sha512)  /tmp/wp-cli.phar" | sha512sum -c -
  install -m 755 /tmp/wp-cli.phar /usr/local/bin/wp
fi

# --- deploy user (shared by all WP projects; sudo limited to reloading php-fpm/nginx)
id deploy &>/dev/null || adduser --disabled-password --gecos "WP deploy" deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
touch /home/deploy/.ssh/authorized_keys
grep -qF "$DEPLOY_PUBKEY" /home/deploy/.ssh/authorized_keys || echo "$DEPLOY_PUBKEY" >> /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys; chmod 600 /home/deploy/.ssh/authorized_keys
cat > /etc/sudoers.d/ember-oak-deploy <<SUD
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php${PHPV}-fpm, /usr/sbin/nginx -t, /usr/bin/systemctl reload nginx
SUD
chmod 440 /etc/sudoers.d/ember-oak-deploy; visudo -cf /etc/sudoers.d/ember-oak-deploy >/dev/null
install -d -o deploy -g www-data -m 755 "$ROOT"
install -d -o deploy -g deploy -m 755 "/var/backups/$PROJECT"

# --- PHP (global ini is the same for every project)
cat > /etc/php/${PHPV}/fpm/conf.d/99-wp-projects.ini <<'INI'
memory_limit=256M
upload_max_filesize=64M
post_max_size=64M
max_execution_time=120
max_input_vars=5000
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
expose_php=Off
INI
rm -f /etc/php/${PHPV}/fpm/conf.d/99-ember-oak.ini   # replaced by the shared file above
cat > /etc/php/${PHPV}/fpm/pool.d/$PROJECT.conf <<POOL
[$PROJECT]
user = deploy
group = www-data
listen = $SOCK
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 6
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
POOL
systemctl enable --now php${PHPV}-fpm
php-fpm${PHPV} -t
systemctl reload php${PHPV}-fpm

# --- MySQL: dedicated db + user per project; password stays on the server
if [ ! -f "$ENVF" ]; then
  PASS=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-28)
  mysql -e "CREATE DATABASE IF NOT EXISTS \`$DBN\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
            CREATE USER IF NOT EXISTS '$DBN'@'localhost' IDENTIFIED BY '${PASS}';
            GRANT ALL PRIVILEGES ON \`$DBN\`.* TO '$DBN'@'localhost'; FLUSH PRIVILEGES;"
  ( umask 077; printf 'DB_NAME=%s\nDB_USER=%s\nDB_PASS=%s\n' "$DBN" "$DBN" "$PASS" > "$ENVF" )
fi

# --- nginx vhost on $SITE_PORT (matched by server_name; existing default_server untouched)
cat > /etc/nginx/sites-available/$PROJECT <<NGX
server {
    listen $SITE_PORT;
    listen [::]:$SITE_PORT;
    server_name $SERVER_NAME;   # add domain(s) here later, then run certbot
    root $ROOT;
    index index.php;
    client_max_body_size 64M;

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    location ~ /\.(?!well-known) { deny all; }
    location = /xmlrpc.php { deny all; }
    location ~* /wp-config\.php { deny all; }
    location ~* ^/wp-content/uploads/.*\.php\$ { deny all; }

    location / { try_files \$uri \$uri/ /index.php?\$args; }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$SOCK;
        fastcgi_read_timeout 120s;
    }
    location ~* \.(?:css|js|woff2?|ttf|svg|webp|avif|png|jpe?g|gif|ico)\$ {
        expires 30d; access_log off; add_header Cache-Control "public";
        try_files \$uri =404;
    }
    gzip on; gzip_types text/css application/javascript application/json image/svg+xml;
}
NGX
chmod 644 /etc/nginx/sites-available/$PROJECT /etc/php/${PHPV}/fpm/pool.d/$PROJECT.conf   # readable config (deploy.sh pre-flight reads the vhost)
ln -sf /etc/nginx/sites-available/$PROJECT /etc/nginx/sites-enabled/$PROJECT
nginx -t
systemctl reload nginx

# --- firewall: open the project port (80/443 are already open)
if [ "$OPEN_FIREWALL" = "1" ] && [ "$SITE_PORT" != "80" ] && command -v ufw >/dev/null; then
  ufw allow "$SITE_PORT/tcp" comment "wp:$PROJECT" >/dev/null && echo "ufw: opened $SITE_PORT/tcp"
fi

# --- WP-Cron -> real cron, one line per project (never touches other projects' lines)
CRON="* * * * * /usr/local/bin/wp cron event run --due-now --path=${ROOT} >/dev/null 2>&1"
{ crontab -u deploy -l 2>/dev/null | grep -vF -- "--path=${ROOT} " || true; echo "$CRON"; } | crontab -u deploy -

echo "SERVER_SETUP_OK project=$PROJECT port=$SITE_PORT root=$ROOT db=$DBN env=$ENVF"
