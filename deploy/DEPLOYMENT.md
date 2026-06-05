# Panduan Deploy GMIM Keuangan ke VPS

> Target: **Ubuntu 24.04 LTS** · MySQL self-host · domain **gmim.web.id** · 4 aplikasi.
> Semua perintah diasumsikan berjalan sebagai **root**. Jika login sebagai user biasa (misal IDCloudHost memberi user non-root), jalankan `sudo -i` terlebih dahulu sebelum memulai, lalu semua perintah di bawah bisa di-copy-paste tanpa perubahan.
> Ganti `gmim.web.id` jika domain berbeda.

## Peta Domain

| Subdomain | Aplikasi | Root |
|-----------|----------|------|
| `gmim.web.id` + `www` | Landing (statis) | `/var/www/gmim_landingpage` |
| `app.gmim.web.id` | Manage (Vue SPA) | `/var/www/gmim_manage/dist` |
| `admin.gmim.web.id` | Admin (Vue SPA) | `/var/www/gmim_admin/dist` |
| `api.gmim.web.id` | Laravel API | `/var/www/gmim_api/public` |

---

## 0. DNS — arahkan domain ke IP VPS

Di panel DNS domain Anda, buat **A record** (ganti `203.0.113.10` dengan IP VPS):

| Type | Name | Value |
|------|------|-------|
| A | `@` | `203.0.113.10` |
| A | `www` | `203.0.113.10` |
| A | `api` | `203.0.113.10` |
| A | `app` | `203.0.113.10` |
| A | `admin` | `203.0.113.10` |

Tunggu propagasi (cek: `dig +short api.gmim.web.id` harus mengembalikan IP VPS) sebelum langkah SSL.

---

## 1. Persiapan Server

```bash
# Login ke VPS
ssh root@203.0.113.10

# Jika login sebagai user non-root (misal IDCloudHost), naik ke root dulu:
sudo -i

# Update sistem
apt update && apt upgrade -y

# Firewall — izinkan SSH + HTTP/HTTPS
apt install -y ufw
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

# Timezone (opsional, WITA untuk Sulut)
timedatectl set-timezone Asia/Makassar
```

---

## 2. Install Stack

```bash
# Nginx
apt install -y nginx

# PHP 8.3 + ekstensi Laravel (repo ondrej untuk versi stabil)
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl

# MySQL 8
apt install -y mysql-server
systemctl enable --now mysql

# Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Node.js 20 LTS (untuk build frontend)
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Certbot (SSL Let's Encrypt)
apt install -y certbot python3-certbot-nginx

# Git
apt install -y git
```

---

## 3. Database

```bash
mysql
```
```sql
CREATE DATABASE gmim_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gmim'@'127.0.0.1' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON gmim_api.* TO 'gmim'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

---

## 4. Deploy API (Laravel)

```bash
cd /var/www
git clone <URL_REPO_API> gmim_api
cd gmim_api

# Dependency produksi (tanpa dev)
composer install --no-dev --optimize-autoloader

# Konfigurasi environment
cp deploy/env.api.production.example .env
nano .env        # isi DB_PASSWORD, pastikan domain benar
php artisan key:generate

# Migrasi + seed (HANYA pertama kali; seed mengisi paket, admin, demo)
php artisan migrate --force --seed

# Symlink storage (untuk file bukti pemasukan)
php artisan storage:link

# Cache konfigurasi/route/view (produksi)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Permission — nginx/php-fpm jalan sebagai www-data
chown -R www-data:www-data /var/www/gmim_api
chmod -R 775 storage bootstrap/cache
```

> **Akun seed produksi:** Super Admin `admin@gmim.app` / `admin123` — **WAJIB ganti password** setelah login pertama (atau hapus seeder demo di produksi nyata).

---

## 5. Queue Worker + Scheduler

```bash
# Queue worker (email, webhook async)
cp /var/www/gmim_api/deploy/gmim-queue.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now gmim-queue
systemctl status gmim-queue        # pastikan "active (running)"

# Scheduler (job harian evaluasi langganan) via cron www-data
crontab -u www-data -e
```
Tambahkan baris:
```cron
* * * * * cd /var/www/gmim_api && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Build & Deploy Frontend

### 6a. Manage (app.gmim.web.id)
```bash
cd /var/www
git clone <URL_REPO_MANAGE> gmim_manage
cd gmim_manage
# .env.production sudah berisi VITE_API_BASE_URL=https://api.gmim.web.id/api
npm ci
npm run build                      # hasil di dist/
chown -R www-data:www-data /var/www/gmim_manage/dist
```

### 6b. Admin (admin.gmim.web.id)
```bash
cd /var/www
git clone <URL_REPO_ADMIN> gmim_admin
cd gmim_admin
npm ci
npm run build
chown -R www-data:www-data /var/www/gmim_admin/dist
```

### 6c. Landing (gmim.web.id) — statis, tanpa build
```bash
cd /var/www
git clone <URL_REPO_LANDING> gmim_landingpage
chown -R www-data:www-data /var/www/gmim_landingpage
# Pastikan index.html: MANAGE_URL produksi = https://app.gmim.web.id (sudah diset)
```

---

## 7. Nginx — pasang 4 site

```bash
cd /var/www/gmim_api/deploy/nginx

# Salin semua config
cp api.gmim.web.id.conf     /etc/nginx/sites-available/api.gmim.web.id
cp app.gmim.web.id.conf     /etc/nginx/sites-available/app.gmim.web.id
cp admin.gmim.web.id.conf   /etc/nginx/sites-available/admin.gmim.web.id
cp landing.gmim.web.id.conf /etc/nginx/sites-available/gmim.web.id

# Aktifkan
ln -s /etc/nginx/sites-available/api.gmim.web.id   /etc/nginx/sites-enabled/
ln -s /etc/nginx/sites-available/app.gmim.web.id   /etc/nginx/sites-enabled/
ln -s /etc/nginx/sites-available/admin.gmim.web.id /etc/nginx/sites-enabled/
ln -s /etc/nginx/sites-available/gmim.web.id       /etc/nginx/sites-enabled/

# Nonaktifkan default
rm -f /etc/nginx/sites-enabled/default

# Uji & reload
nginx -t && systemctl reload nginx
```

---

## 8. SSL (Let's Encrypt)

Setelah DNS sudah mengarah ke VPS (cek dengan `dig`):

```bash
certbot --nginx \
  -d gmim.web.id -d www.gmim.web.id \
  -d api.gmim.web.id \
  -d app.gmim.web.id \
  -d admin.gmim.web.id \
  --redirect --agree-tos -m admin@gmim.web.id --no-eff-email
```

Certbot otomatis menambahkan blok HTTPS (443) + redirect HTTP→HTTPS ke tiap config. Perpanjangan otomatis lewat systemd timer (`systemctl status certbot.timer`).

---

## 9. Verifikasi

```bash
# API sehat
curl -s https://api.gmim.web.id/api/health
# → {"status":"ok","checks":{"db":"ok","queue":"ok"},...}

# Frontend
curl -sI https://app.gmim.web.id   | head -1   # 200
curl -sI https://admin.gmim.web.id | head -1   # 200
curl -sI https://gmim.web.id       | head -1   # 200
```

**Uji manual di browser:**
1. `https://gmim.web.id` → klik "Daftar Gratis" → harus menuju `app.gmim.web.id/daftar`
2. `https://app.gmim.web.id/login` → login `bendahara@bethesda.gmim` / `bendahara123`
3. `https://admin.gmim.web.id/login` → login `admin@gmim.app` / `admin123` → **ganti password**

---

## 10. Update / Redeploy

**API:**
```bash
cd /var/www/gmim_api
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force          # tanpa --seed!
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
systemctl restart gmim-queue         # restart worker agar pakai kode baru
```

**Manage / Admin:**
```bash
cd /var/www/gmim_manage   # atau gmim_admin
git pull && npm ci && npm run build
chown -R www-data:www-data dist
```

**Landing:**
```bash
cd /var/www/gmim_landingpage && git pull
```

---

## Checklist Go-Live (Produksi Nyata)

- [ ] `APP_DEBUG=false`, `APP_ENV=production` di `.env`
- [ ] Password Super Admin diganti; pertimbangkan hapus user `demo@gmim.app` jika tak dipakai
- [ ] Backup DB terjadwal: `mysqldump` harian via cron + simpan off-site
- [ ] `MAIL_MAILER` diganti dari `log` ke SMTP provider (verifikasi email & invoice butuh ini)
- [ ] Midtrans platform key diisi bila mengaktifkan pembayaran langganan
- [ ] Monitoring: cek `journalctl -u gmim-queue -f` & `/var/log/nginx/*error.log`
- [ ] Rate limit & captcha login (sudah ada throttle backend; captcha menyusul)
- [ ] Test restore backup minimal 1× (backup tak teruji = belum ada backup)

---

## Troubleshooting Cepat

| Gejala | Cek |
|--------|-----|
| 502 Bad Gateway di API | `systemctl status php8.3-fpm`; path socket di nginx (`php8.3-fpm.sock`) |
| CORS error di browser | `CORS_ALLOWED_DOMAIN=gmim.web.id` di `.env` + `php artisan config:cache` |
| SPA refresh → 404 | Pastikan `try_files ... /index.html` di config app/admin |
| Bukti gambar 404 | `php artisan storage:link` sudah dijalankan; cek symlink `public/storage` |
| Email tak terkirim | `MAIL_MAILER` masih `log` → cek `storage/logs/laravel.log`; ganti ke SMTP |
| Job langganan tak jalan | Cron `schedule:run` aktif + `gmim-queue` running |
| Migrasi gagal saat update | Cek `php artisan migrate:status`; jangan pakai `--seed` saat redeploy |
