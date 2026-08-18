# Deploy YokPrinting ke cPanel (domain + hosting satu provider)

Panduan ini khusus untuk setup kamu: Laravel 12, MySQL, cPanel dengan Terminal
+ Git Version Control aktif, domain & hosting dari provider yang sama.

Ganti `USERNAME` dengan username cPanel kamu, dan `DOMAIN` dengan domain kamu
di semua contoh di bawah.

## 0. Ringkasan struktur

Laravel butuh document root di folder `public/`, tapi cPanel mengarahkan
`public_html/` ke root domain. Solusinya: source code Laravel disimpan di
folder **di luar** `public_html` (misal `~/yokprinting_app`), lalu isi
`public/` disinkron ke `public_html/` setiap deploy. Ini juga lebih aman
karena `.env`, `app/`, `vendor/` jadi tidak bisa diakses langsung lewat URL.

```
/home/USERNAME/
├── yokprinting_app/      ← source code Laravel (git repo, .env di sini)
│   ├── app/
│   ├── vendor/
│   ├── public/           ← isi folder ini yang disinkron ke public_html
│   └── .env
└── public_html/          ← document root domain, isi dari public/ + index.php custom
```

## 1. Domain & SSL

Karena domain & hosting satu provider, nameserver biasanya sudah otomatis
mengarah ke hosting kamu — tinggal cek di cPanel:

1. **Domains** → pastikan domain kamu muncul (sebagai primary domain atau
   Addon Domain). Kalau belum ada, tambahkan lewat **Addon Domains**.
2. Tunggu propagasi DNS (biasanya cepat kalau satu provider, bisa sampai
   beberapa jam).
3. Setelah domain aktif, aktifkan **SSL/TLS Status** → **Run AutoSSL** (atau
   **Let's Encrypt™ SSL** kalau tersedia) supaya dapat HTTPS gratis.

## 2. Buat database MySQL

Di cPanel → **MySQL Databases**:

1. Buat database, misal `yokprinting` → jadi `USERNAME_yokprinting`.
2. Buat user MySQL + password kuat.
3. Add user ke database dengan **All Privileges**.
4. Catat: nama database, username, password lengkap (dengan prefix
   `USERNAME_`).

## 3. Setup Git Version Control

Di cPanel → **Git™ Version Control** → **Create**:

- **Clone a Repository**: centang, isi Repository URL dengan URL GitHub kamu
  (`https://github.com/rickyaaaa/yokprinting.git`). Kalau repo private, perlu
  setup deploy key/token dulu (cPanel biasanya kasih SSH public key untuk
  ditambahkan ke GitHub → Settings → Deploy keys).
- **Repository Path**: `/home/USERNAME/yokprinting_app`
- **Repository Name**: bebas, misal `yokprinting`
- Branch: `main`

Setelah repo ter-clone, buka repo tersebut di cPanel dan klik
**Manage** → **Pull or Deploy** → **Update from Remote**, lalu
**Deploy HEAD Commit**. Ini akan menjalankan task di file
[.cpanel.yml](.cpanel.yml) yang sudah disiapkan di repo (edit dulu
`USERNAME` di file itu sebelum deploy pertama, commit & push).

> `.cpanel.yml` otomatis menjalankan `composer install`, sync folder
> `public/`, `migrate --force`, `storage:link`, dan cache config/route/view
> setiap kali kamu klik Deploy. Ia **tidak** menyentuh `.env` atau
> `public_html/index.php` — dua itu kamu setup sekali secara manual (langkah
> 4 & 5).

## 4. Setup `.env` di server (sekali saja)

Lewat cPanel **Terminal** (atau File Manager):

```bash
cd ~/yokprinting_app
cp .env.example .env
php artisan key:generate
```

Edit `.env` (lewat File Manager atau `nano .env` di Terminal), minimal ubah:

```env
APP_NAME=YokPrinting
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMAIN

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=USERNAME_yokprinting
DB_USERNAME=USERNAME_dbuser
DB_PASSWORD="password-kuat-kamu"

SESSION_SECURE_COOKIE=true
```

`QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`
di `.env.example` sudah pas untuk shared hosting (tidak butuh Redis).

Kalau nanti mau kirim email asli (reset password, dll), tambahkan
`MAIL_MAILER=smtp` + kredensial SMTP dari cPanel (**Email Accounts**) atau
layanan seperti Mailgun/SES. Sekarang default `log` jadi email tidak benar-
benar terkirim, cuma dicatat di log.

## 5. Hubungkan `public_html` ke folder `public/`

Karena kita pakai folder terpisah, `public_html/index.php` perlu di-edit
sekali supaya path-nya menunjuk ke `yokprinting_app`:

```bash
# copy isi public/ (termasuk index.php) ke public_html sekali di awal
rsync -a ~/yokprinting_app/public/ ~/public_html/
```

Lalu edit `~/public_html/index.php`, ganti dua baris path:

```php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
```

menjadi:

```php
require __DIR__.'/../yokprinting_app/vendor/autoload.php';
$app = require_once __DIR__.'/../yokprinting_app/bootstrap/app.php';
```

Setelah ini, deploy berikutnya lewat `.cpanel.yml` **tidak akan menimpa**
`index.php` ini (sudah di-exclude), jadi cukup dilakukan sekali.

## 6. Build asset frontend (Vite)

Kebanyakan shared hosting cPanel tidak punya Node.js/npm siap pakai di
Terminal untuk build Vite. Cara paling aman: **build di komputer lokal**,
lalu commit hasil build sebelum push:

```powershell
npm ci
npm run build
git add -f public/build
git commit -m "chore: build production assets"
git push
```

`public/build` memang di-gitignore untuk dev sehari-hari — pakai `-f` khusus
saat mau deploy supaya masuk ke commit yang di-pull cPanel. Ulangi ini setiap
kali ada perubahan di `resources/js` atau `resources/css`.

## 7. Cron jobs (scheduler + queue worker)

Di cPanel → **Cron Jobs**, tambahkan dua entry (sesuaikan path PHP binary
dengan versi PHP 8.2 di hosting kamu — cek di **MultiPHP Manager**, biasanya
`/usr/local/bin/php` atau `/opt/cpanel/ea-php82/root/usr/bin/php`):

**Laravel scheduler** (tiap menit — menjalankan `routes/console.php`: cek
invoice overdue, follow-up pelanggan, cleanup expense proof, dll):

```
* * * * * /usr/local/bin/php /home/USERNAME/yokprinting_app/artisan schedule:run >> /dev/null 2>&1
```

**Queue worker** (aplikasi ini pakai `QUEUE_CONNECTION=database` dengan job
seperti `MarkOverdueInvoicesJob`, `UpdateCustomerFollowUpStatusesJob`, dll
yang implement `ShouldQueue` — jadi butuh worker, bukan cuma scheduler).
Karena shared hosting tidak boleh menjalankan proses `queue:work` terus-
menerus, jalankan tiap 5 menit dengan `--stop-when-empty`:

```
*/5 * * * * /usr/local/bin/php /home/USERNAME/yokprinting_app/artisan queue:work --stop-when-empty --max-time=280 --tries=3 >> /dev/null 2>&1
```

## 8. Cek akhir

- Buka `https://DOMAIN/up` → harus dapat response 200 (Laravel health check).
- Login ke app, coba upload logo perusahaan (test `storage:link` jalan).
- Cek `storage/logs/laravel.log` di Terminal kalau ada error 500.
- Pastikan `APP_DEBUG=false` di production (jangan bocorkan stack trace).

## Update berikutnya (alur normal)

1. `git push` ke `main` seperti biasa.
2. Kalau ada perubahan frontend: build asset lokal dulu (langkah 6) sebelum
   push.
3. Di cPanel → Git Version Control → repo kamu → **Update from Remote** →
   **Deploy HEAD Commit**.
