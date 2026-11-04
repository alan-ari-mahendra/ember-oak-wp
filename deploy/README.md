# Deploy — Ember & Oak (VPS Brata)

Only `wp-content/plugins/ember-oak-core/` is deployed by CI. WordPress core, third‑party plugins, uploads and the
database live on the server and are NOT touched by CI.

## One-time (already done for 46.250.232.141)
1. `deploy/server-setup.sh` — PHP 8.3-FPM, `deploy` user, DB `ember_oak`, nginx vhost (matched by IP), WP-Cron → system cron.
2. `deploy/migrate-once.sh` — copy local DB/uploads/plugins, rewrite URLs, first plugin deploy.

## GitHub secrets (Settings → Secrets and variables → Actions; create environment `production`)
| Secret | Value |
|---|---|
| `SSH_PRIVATE_KEY` | contents of `~/.ssh/ember-oak-deploy` (deploy-only key, user `deploy`, NOT root) |
| `SSH_HOST` | `46.250.232.141` |
| `SSH_USER` | `deploy` |
| `SSH_KNOWN_HOSTS` | `46.250.232.141 ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAII44DTggChm+g71s9joWJWXyNuHIXSpsdXFz0HMrixQ1` (fingerprint `SHA256:rMdou0m09nBukBtqbAdcpROR0u3kEKgxHTLqXyhlbo0`) |
| `SITE_URL` | `http://46.250.232.141` or with a custom port `http://46.250.232.141:8081` (use `https://your-domain` later) |
| `SITE_PORT` *(optional)* | nginx port of this project on the VPS, e.g. `8081`. Empty = port from `SITE_URL`, else 80/443 |
| `REMOTE_ROOT` *(optional)* | project directory, e.g. `/var/www/my-other-site`. Empty = `/var/www/ember-oak` |
| `PLUGIN_DIR` *(optional)* | custom plugin folder deployed from `wp-content/plugins/`. Empty = `ember-oak-core` |

## Flow
push to `main` (paths filter) → **lint** (php -l, node --check, bash -n, secret grep) → **deploy**: tar backup of the current plugin
(`/var/backups/ember-oak`, last 5 kept) → rsync → `wp cache flush`, `wp elementor flush-css`, reload php-fpm →
health check on the server (home has `eo-header`, `/wp-json/`, product page) → on failure automatic rollback and red job.

## Manual deploy / rollback
```bash
DEPLOY_HOST=46.250.232.141 DEPLOY_USER=deploy SSH_OPTS="-i ~/.ssh/ember-oak-deploy -o IdentitiesOnly=yes" ./deploy/deploy.sh
# rollback to a backup
ssh deploy@46.250.232.141 'ls -t /var/backups/ember-oak; cd /var/www/ember-oak/wp-content/plugins && rm -rf ember-oak-core && tar -xzf /var/backups/ember-oak/<file>.tgz'
```

## Adding a domain later
Add the domain to `server_name` in `/etc/nginx/sites-available/ember-oak`, point DNS A record to the VPS, `apt install certbot python3-certbot-nginx`,
`certbot --nginx -d domain`, then `wp search-replace http://46.250.232.141 https://domain --all-tables --skip-columns=guid`,
update `WP_HOME`/`WP_SITEURL` in `wp-config.php`, set `blog_public` to 1, and update the `SITE_URL` secret.

## Hosting many WordPress projects on this VPS
Each project gets its own directory, php-fpm pool/socket, MySQL database+user, nginx vhost, backups folder and WP-Cron line.
A port can serve only one project **per `server_name`**, so with IP-only access every extra project needs its own port;
with domains, many projects can share 80/443 (recommended long term).

```bash
# 1) provision (from the repo root; picks any free port, refuses ports used by other apps)
ssh root@46.250.232.141 "DEPLOY_PUBKEY='$(cat ~/.ssh/ember-oak-deploy.pub)' PROJECT=my-other-site SITE_PORT=8081 bash -s" < deploy/server-setup.sh
# 2) one-time content migration for that project (or a fresh `wp core install` on the server)
PROJECT=my-other-site SITE_PORT=8081 OLD_URL=http://other.test PLUGIN_DIR=my-plugin ./deploy/migrate-once.sh
# 3) in that project's GitHub repo set secrets: SSH_PRIVATE_KEY, SSH_HOST, SSH_USER, SSH_KNOWN_HOSTS,
#    SITE_URL=http://46.250.232.141:8081, SITE_PORT=8081, REMOTE_ROOT=/var/www/my-other-site, PLUGIN_DIR=my-plugin
```
Ports already taken on this VPS: 22, 80, 443, 3000, 3001, 8000, 5678 (local), 3306/33060 (local). `server-setup.sh` checks this itself and opens the new port in UFW (`OPEN_FIREWALL=0` to skip).
Isolation note: all projects share the `deploy` unix user (simple). For hostile/untrusted projects give each one its own user and pool `user=`.
The health check defaults (`eo-header` marker, `/product/guji-natural/`) are specific to this site; other projects set `HEALTH_MARKER` / `HEALTH_PATH` env in their workflow.
