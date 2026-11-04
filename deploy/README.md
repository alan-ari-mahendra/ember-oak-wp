# Panduan Deploy — Ember & Oak (VPS Brata)

Dokumen ini menjelaskan cara kerja deploy, cara mengisi GitHub Secrets, cara deploy/rollback manual, dan troubleshooting.

- **Situs:** http://46.250.232.141:8632
- **Server:** VPS "Brata", Ubuntu 24.04, `46.250.232.141`
- **Repo:** `alan-ari-mahendra/ember-oak-wp` (branch `main`)

## 1. Apa yang di-deploy

CI/CD **hanya** men-deploy plugin custom `wp-content/plugins/ember-oak-core/`.
WordPress core, plugin pihak ketiga, tema, uploads, dan database ada di server dan **tidak disentuh** CI.
Konten (halaman Elementor, produk, field SCF) tersimpan di database, bukan di git.

## 2. Alur deploy otomatis

Pemicu: `git push` ke `main` yang mengubah `wp-content/plugins/ember-oak-core/**`, `deploy/**`, atau `.github/workflows/deploy.yml`.
Bisa juga manual: tab **Actions → Deploy to VPS → Run workflow**.

```
push main
  └─ Lint        php -l, node --check, bash -n, cek secret tertanam
  └─ Deploy      (environment: production)
       1. pre-flight: /var/www/ember-oak adalah instalasi WP
       2. pre-flight: nginx benar-benar listen di SITE_PORT
       3. backup plugin lama -> /var/backups/ember-oak/*.tgz (5 terakhir disimpan)
       4. rsync --delete ke wp-content/plugins/ember-oak-core/
       5. wp cache flush, wp elementor flush-css, reload php8.3-fpm
       6. health check di server: home berisi "eo-header", /wp-json/, halaman produk
       7. gagal -> rollback otomatis dari backup, job merah
```

## 3. GitHub Secrets

Buka repo → **Settings → Secrets and variables → Actions**. Buat juga environment `production`
(Settings → Environments). Tambahkan secret di **Repository secrets** (atau di environment `production`).

| Secret | Wajib | Nilai saat ini | Keterangan |
|---|---|---|---|
| `SSH_PRIVATE_KEY` | ya | isi file `~/.ssh/ember-oak-ci-root` | Private key CI untuk user root (lihat 3.1) |
| `SSH_HOST` | ya | `46.250.232.141` | IP atau domain VPS |
| `SSH_USER` | ya | `root` | User SSH. `deploy` juga didukung (lebih terbatas) |
| `SSH_KNOWN_HOSTS` | ya | lihat 3.2 | Host key server, dipin (tanpa `StrictHostKeyChecking=no`) |
| `SITE_URL` | ya | `http://46.250.232.141:8632` | URL publik situs. Dipakai untuk host dan port health check |
| `SITE_PORT` | opsional | `8632` | Port nginx project ini. Kosong = diambil dari `SITE_URL`, lalu 80/443 |
| `REMOTE_ROOT` | opsional | `/var/www/ember-oak` | Folder situs di server. **Tanpa spasi** di awal/akhir |
| `PLUGIN_DIR` | opsional | `ember-oak-core` | Folder plugin custom yang di-deploy |

> Nilai `SITE_PORT` harus sama dengan port `listen` di vhost nginx. Kalau beda, deploy berhenti di pre-flight
> dengan pesan `SITE_PORT=... does not match the server`.

### 3.1 Membuat dan memasang key CI

Gunakan key khusus CI, jangan memakai key pribadi Anda.

```fish
# 1) buat key (tanpa passphrase)
ssh-keygen -t ed25519 -N '' -C 'github-actions-root' -f ~/.ssh/ember-oak-ci-root

# 2) pasang public key di server, dibatasi dengan "restrict" (tanpa port-forwarding/agent/TTY)
set PUB (cat ~/.ssh/ember-oak-ci-root.pub)
ssh sukun-vps-brata "echo 'restrict $PUB' >> /root/.ssh/authorized_keys"

# 3) tes
ssh -i ~/.ssh/ember-oak-ci-root -o IdentitiesOnly=yes root@46.250.232.141 whoami   # harus: root

# 4) salin private key ke clipboard, lalu tempel ke secret SSH_PRIVATE_KEY
cat ~/.ssh/ember-oak-ci-root | wl-copy
```

Tempel **seluruh** isi file, termasuk baris `-----BEGIN OPENSSH PRIVATE KEY-----` dan `-----END ...-----`.
Setelah itu kosongkan clipboard. Jangan pernah commit atau mengirim private key lewat chat.

### 3.2 Mengambil `SSH_KNOWN_HOSTS`

```fish
ssh-keyscan -t ed25519 46.250.232.141 | wl-copy
```

Hasilnya satu baris `46.250.232.141 ssh-ed25519 AAAA...`. Pastikan fingerprint-nya cocok dengan server:

```fish
ssh sukun-vps-brata 'ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub'
# SHA256:rMdou0m09nBukBtqbAdcpROR0u3kEKgxHTLqXyhlbo0
```

### 3.3 Mengisi secret dengan aman

Nilai pendek bisa disalin tanpa newline agar tidak ada karakter tersembunyi:

```fish
echo -n 8632 | wl-copy
echo -n /var/www/ember-oak | wl-copy
echo -n http://46.250.232.141:8632 | wl-copy
```

Kesalahan umum: spasi di awal nilai (misalnya `REMOTE_ROOT`) atau `SITE_PORT` yang tidak sama dengan port nginx.

### 3.4 Mengamankan akses root di GitHub

Karena CI memakai key root, siapa pun yang bisa mengubah workflow bisa mengendalikan VPS.

- Settings → Environments → `production`: aktifkan **Required reviewers** dan batasi **Deployment branches** ke `main`.
- Aktifkan branch protection di `main`.
- Jangan beri akses write ke repo ini ke orang yang tidak dipercaya.
- Alternatif yang lebih aman: `SSH_USER=deploy` dengan key `~/.ssh/ember-oak-deploy` (hak akses terbatas).

## 4. Deploy rutin

1. Ubah kode di `wp-content/plugins/ember-oak-core/`.
2. Commit dan push:
   ```fish
   git add wp-content/plugins/ember-oak-core
   git commit -m "feat: ..."
   git push origin main
   ```
3. Pantau di tab **Actions**. Hijau berarti plugin baru sudah live.
4. Cek: http://46.250.232.141:8632

## 5. Deploy manual dari terminal

```fish
cd /var/www/ember-oak
env DEPLOY_HOST=46.250.232.141 DEPLOY_USER=root \
    SITE_URL=http://46.250.232.141:8632 SITE_PORT=8632 REMOTE_ROOT=/var/www/ember-oak \
    SSH_OPTS="-i $HOME/.ssh/ember-oak-ci-root -o IdentitiesOnly=yes" \
    ./deploy/deploy.sh
```

Output yang diharapkan diakhiri `HEALTH_OK`.

## 6. Rollback manual

Rollback otomatis terjadi saat health check gagal. Untuk rollback manual:

```fish
ssh sukun-vps-brata 'ls -t /var/backups/ember-oak/*.tgz | head'
ssh sukun-vps-brata 'cd /var/www/ember-oak/wp-content/plugins \
  && rm -rf ember-oak-core \
  && tar -xzf /var/backups/ember-oak/NAMA-FILE.tgz \
  && chown -R deploy:www-data ember-oak-core \
  && systemctl reload php8.3-fpm'
```

Backup database sebelum perubahan URL/port ada di `/var/backups/ember-oak/db-before-port-*.sql` (izin 600, hanya root).

## 7. Troubleshooting

| Gejala di log Actions | Penyebab | Solusi |
|---|---|---|
| `SITE_PORT=... does not match the server` | Secret `SITE_PORT` bukan port nginx | Samakan dengan port `listen` di vhost (sekarang `8632`) |
| `curl: (7) Failed to connect to 127.0.0.1 port ...` | Port salah / nginx tidak listen | Cek `ss -tln`, perbaiki `SITE_PORT` |
| `HEALTH_FAILED -> rolling back` | Halaman tidak berisi `eo-header`, `/wp-json/` atau halaman produk error | Lihat `/var/log/nginx/error.log` dan `/var/log/php8.3-fpm.log`, perbaiki kode, push lagi |
| Health check 301 | Header `Host` tanpa port, sedangkan `WP_HOME` memakai port | Sudah ditangani script (Host = `host:port`). Pastikan `SITE_URL` memuat port |
| `Permission denied (publickey)` | `SSH_PRIVATE_KEY` salah / belum terpasang di server | Ulangi 3.1, pastikan seluruh isi file ditempel |
| `Host key verification failed` | `SSH_KNOWN_HOSTS` salah | Ulangi 3.2 |
| `REMOTE_ROOT` error / folder aneh | Ada spasi di nilai secret | Isi ulang dengan `echo -n ... \| wl-copy` |
| Deploy tidak jalan setelah push | Perubahan di luar path filter | Jalankan manual: Actions → Run workflow |

Memeriksa server saat deploy gagal:

```fish
ssh sukun-vps-brata 'ss -tln | grep -E ":(80|8632) "; nginx -t; tail -n 30 /var/log/nginx/error.log'
ssh sukun-vps-brata 'ls -t /var/backups/ember-oak | head -5'
```

## 8. Setup server (sudah dilakukan, untuk referensi)

Semua langkah di bawah dijalankan sekali sebagai root dari mesin lokal.

1. `deploy/server-setup.sh`: PHP 8.3-FPM + ekstensi, wp-cli, user `deploy`, pool FPM per project, database dan user MySQL,
   vhost nginx, aturan UFW, dan WP-Cron diganti cron sistem per project.
   ```fish
   ssh sukun-vps-brata "DEPLOY_PUBKEY='"(cat ~/.ssh/ember-oak-deploy.pub)"' PROJECT=ember-oak SITE_PORT=8632 bash -s" < deploy/server-setup.sh
   ```
2. `deploy/migrate-once.sh`: salin database, uploads dan plugin dari lokal, ganti URL, deploy pertama.
3. Port 8632 ditambahkan manual ke vhost `/etc/nginx/sites-available/ember-oak` (`listen 8632;`), UFW dibuka untuk `8632/tcp`,
   `WP_HOME`/`WP_SITEURL` di `wp-config.php` diubah, dan URL di database diganti dengan `wp search-replace`.
   Port 80 masih aktif dan mengalihkan (301) ke 8632.

## 9. Pindah ke domain (nanti)

1. Arahkan DNS A record domain ke `46.250.232.141`.
2. Tambahkan domain ke `server_name` di `/etc/nginx/sites-available/ember-oak`, lalu `nginx -t && systemctl reload nginx`.
3. `apt install certbot python3-certbot-nginx` lalu `certbot --nginx -d domain.com`.
4. Ganti URL:
   ```fish
   ssh sukun-vps-brata 'cd /var/www/ember-oak && sudo -u deploy wp search-replace "http://46.250.232.141:8632" "https://domain.com" --all-tables --skip-columns=guid && sudo -u deploy wp elementor flush-css'
   ```
   Ubah juga `WP_HOME`/`WP_SITEURL` di `wp-config.php`, atur `blog_public` ke 1, lalu update secret `SITE_URL` (dan hapus `SITE_PORT`).

## 10. Project WordPress lain di VPS yang sama

Setiap project punya folder, pool PHP-FPM, database, vhost nginx, folder backup, dan baris cron sendiri.
Dengan akses via IP, tiap project butuh port sendiri. Dengan domain, banyak project bisa berbagi 80/443.

```fish
# 1) provisioning (pilih port bebas)
ssh sukun-vps-brata "DEPLOY_PUBKEY='"(cat ~/.ssh/ember-oak-deploy.pub)"' PROJECT=my-other-site SITE_PORT=8633 bash -s" < deploy/server-setup.sh
# 2) migrasi konten sekali
env PROJECT=my-other-site SITE_PORT=8633 OLD_URL=http://other.test PLUGIN_DIR=my-plugin ./deploy/migrate-once.sh
# 3) di repo project itu, set secrets:
#    SSH_PRIVATE_KEY, SSH_HOST, SSH_USER, SSH_KNOWN_HOSTS,
#    SITE_URL=http://46.250.232.141:8633, SITE_PORT=8633, REMOTE_ROOT=/var/www/my-other-site, PLUGIN_DIR=my-plugin
```

Port yang sudah dipakai di VPS ini: 22, 80, 443, 3000, 3001, 8000, 5678 (lokal), 3306/33060 (lokal), 8632 (Ember & Oak).
Untuk project lain, atur `HEALTH_MARKER` dan `HEALTH_PATH` di workflow karena default-nya khusus situs ini
(`eo-header`, `/product/guji-natural/`).

## 11. Checklist keamanan

- [ ] Ganti password root VPS dan matikan login password SSH (`PasswordAuthentication no`).
- [ ] Required reviewers + branch `main` di environment `production`.
- [ ] Ganti password admin WordPress di server (sama dengan password lokal).
- [ ] Port 3000/3001/8000 terbuka publik di UFW; tinjau apakah memang perlu.
- [ ] Setelah domain dan HTTPS siap: `blog_public=1`, tutup port 80 lama, update `SITE_URL`.
