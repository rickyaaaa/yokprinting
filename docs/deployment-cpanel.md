# Deploy YokPrinting ke cPanel/hPanel — yoksystem.id

Status: **sudah live** di https://yoksystem.id sejak 2026-08-18 (deploy pertama
dilakukan langsung lewat SSH). Dokumen ini menjelaskan setup yang sudah
berjalan, supaya deploy berikutnya (dan siapa pun yang pegang server ini)
tahu persis apa yang terjadi dan kenapa.

Hosting: Hostinger (akun `u433850416`), layout `domains/<domain>/public_html`
(bukan `~/public_html` langsung seperti cPanel generic).

## 0. Struktur yang dipakai

```
/home/u433850416/domains/yoksystem.id/
├── yokprinting_app/       ← source code Laravel (git repo, .env di sini)
│   ├── app/
│   ├── vendor/
│   ├── public/            ← isi folder ini yang disinkron ke public_html
│   └── .env
└── public_html/           ← document root domain yoksystem.id
    ├── index.php          ← COPY hasil edit manual, path diarahkan ke ../yokprinting_app
    ├── build/              ← hasil `npm run build`, disinkron dari public/build
    ├── storage -> /home/u433850416/domains/yoksystem.id/yokprinting_app/storage/app/public
    └── ...isi public/ lainnya
```

App code disimpan di luar `public_html` supaya `.env`, `app/`, `vendor/`
tidak bisa diakses langsung lewat URL.

## 1. Domain, SSL, Database (sudah dibuat)

- Domain `yoksystem.id` sudah aktif di akun hosting yang sama dengan domain
  dibeli → nameserver otomatis connect, AutoSSL Hostinger sudah aktif
  (`https://yoksystem.id` sudah HTTPS).
- Database: `u433850416_yoksystem`, user `u433850416_yoksystem` (privilese
  penuh ke database itu saja). Password ada di password manager kamu — kalau
  lupa, reset lewat hPanel → Databases.

## 2. Kenapa pakai SSH langsung, bukan Git Version Control UI

Rencana awal pakai fitur **Git Version Control** cPanel + `.cpanel.yml`
auto-deploy. Setelah cek lapangan, akun ini punya **Terminal/SSH** yang
lengkap (composer, git, rsync tersedia), jadi deploy pertama dilakukan
langsung lewat SSH — lebih cepat dan bisa diverifikasi tiap langkah. Kalau
mau tetap otomatisasi lewat Git Version Control UI cPanel di kemudian hari,
[.cpanel.yml](../.cpanel.yml) di root repo sudah disiapkan dan path-nya sudah
disesuaikan dengan struktur `domains/yoksystem.id/...` di atas.

## 3. Dua keterbatasan penting di host ini

**a. `symlink()` PHP di-disable** (kebijakan keamanan CageFS Hostinger), jadi
`php artisan storage:link` **selalu gagal** dengan error
`Call to undefined function Illuminate\Filesystem\exec()`. Solusinya: symlink
dibuat manual lewat shell (`ln -s`, bukan lewat PHP) **sekali saja**, pakai
absolute path supaya tetap valid walau `public/` disinkron ulang ke
`public_html`:

```bash
cd ~/domains/yoksystem.id/yokprinting_app
ln -s /home/u433850416/domains/yoksystem.id/yokprinting_app/storage/app/public public/storage
```

Symlink ini permanen — **jangan** jalankan `php artisan storage:link` lagi di
server ini, itu hanya akan menulis error ke log tanpa merusak apa pun (symlink
yang sudah ada tidak akan ketimpa berhasil, tapi command-nya exit dengan
error). `.cpanel.yml` sengaja tidak memanggil `storage:link`.

**b. `crontab` command tidak tersedia di SSH** (`bash: crontab: command not
found`). Cron job **harus** ditambahkan lewat hPanel web UI → **Advanced** →
**Cron Jobs**, bukan lewat SSH. Lihat langkah 5.

## 4. Alur deploy (setelah setup awal ini)

```bash
# 1. Kalau ada perubahan frontend, build dulu di lokal:
npm run build
git add -f public/build   # public/build di-gitignore untuk dev sehari-hari
git commit -m "chore: build production assets"
git push

# 2. SSH ke server, pull & install:
ssh -p 65002 u433850416@72.61.212.217
cd ~/domains/yoksystem.id/yokprinting_app
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Sync public/ (termasuk build/) ke public_html, TANPA menimpa index.php:
rsync -a --exclude='index.php' \
  ~/domains/yoksystem.id/yokprinting_app/public/ \
  ~/domains/yoksystem.id/public_html/
```

`index.php` di `public_html` sudah di-edit manual sekali (path autoload &
bootstrap diarahkan ke `../yokprinting_app/...`) dan tidak perlu disentuh lagi
selama layout folder tidak berubah.

## 5. Cron jobs (hPanel → Advanced → Cron Jobs)

Tambahkan dua entry ini (PHP CLI di host ini ada di `/usr/bin/php`, versi
8.2.31 — sudah dicek cocok dengan `composer.json` yang minta `^8.2`):

**Laravel scheduler** (tiap menit — jalanin `routes/console.php`: cek invoice
overdue, follow-up pelanggan, cleanup expense proof, heartbeat operasional):

```
* * * * * /usr/bin/php /home/u433850416/domains/yoksystem.id/yokprinting_app/artisan schedule:run >> /dev/null 2>&1
```

**Queue worker** (app ini pakai `QUEUE_CONNECTION=database` dengan job seperti
`MarkOverdueInvoicesJob`, `UpdateCustomerFollowUpStatusesJob`,
`RetryExpenseProofCleanupJob`, dll yang `ShouldQueue` — butuh worker, bukan
cuma scheduler. Karena shared hosting tidak boleh menjalankan `queue:work`
terus-menerus, jalankan tiap 5 menit dengan `--stop-when-empty`):

```
*/5 * * * * /usr/bin/php /home/u433850416/domains/yoksystem.id/yokprinting_app/artisan queue:work --stop-when-empty --max-time=280 --tries=3 >> /dev/null 2>&1
```

> **Status: belum ditambahkan** per deploy pertama (SSH tidak bisa
> `crontab`). Ini satu-satunya langkah manual yang masih perlu dilakukan lewat
> hPanel.

## 6. Cek akhir (sudah diverifikasi saat deploy pertama)

- ✅ `https://yoksystem.id/up` → HTTP 200
- ✅ `https://yoksystem.id/login` → HTTP 200
- ✅ Asset build (`/build/assets/*.css`, `*.js`) → HTTP 200
- ✅ Migrasi database sukses (semua tabel ter-buat)
- ✅ `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`
- ⬜ Cron scheduler + queue worker (lihat langkah 5 — masih perlu ditambahkan
  manual di hPanel)
- ⬜ Login manual & coba upload logo perusahaan (test symlink storage jalan)
- ⬜ Setup `MAIL_MAILER` kalau butuh email asli (sekarang default `log`, email
  tidak benar-benar terkirim)

## 7. Keamanan: rotate kredensial

Password SSH & database akun ini sempat dikirim lewat chat AI assistant untuk
proses deploy. **Disarankan ganti password SSH/hPanel** (hPanel → Advanced →
SSH Access → Change Password) setelah setup awal ini selesai, supaya
kredensial lama tidak nyantol di riwayat percakapan mana pun.
