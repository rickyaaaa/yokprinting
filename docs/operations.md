# Operasional YokPrinting

Lifecycle bukti Pengeluaran membutuhkan dua proses aplikasi yang berjalan terus-menerus:

- Laravel scheduler: jalankan `php artisan schedule:work` saat development, atau cron `php artisan schedule:run` setiap menit saat deployment.
- Laravel queue worker: jalankan `php artisan queue:work` karena purge, retry cleanup, dan heartbeat worker memakai queue.

Gunakan `php artisan operations:health` untuk memeriksa: koneksi database,
status migrasi (ada yang belum dijalankan atau tidak), dan heartbeat
scheduler + worker. Command keluar dengan status gagal bila salah satu
komponen bermasalah, atau bila heartbeat scheduler/worker belum pernah aktif
atau sudah melewati `OPERATIONS_HEALTH_MAX_AGE_MINUTES`. Cache harus dibagi
antar-instance jika aplikasi dijalankan pada lebih dari satu instance.

**Post-deploy verification**: segera setelah deploy, cron scheduler belum
tentu sempat tick, jadi heartbeat wajar masih stale/kosong — itu bukan tanda
deploy gagal. Jalankan `php artisan operations:health --skip-heartbeat`
langsung setelah deploy untuk memverifikasi hanya aplikasi/database/migrasi
(tanpa false-positive dari heartbeat yang belum sempat update), lalu jalankan
`php artisan operations:health` (tanpa flag) beberapa menit kemudian untuk
memastikan scheduler dan queue worker juga sudah aktif.

Gunakan `php artisan expenses:proofs:scan` untuk dry-run pencarian bukti yatim. Grace period default dikonfigurasi melalui `EXPENSE_PROOF_ORPHAN_GRACE_MINUTES`; penghapusan hanya terjadi bila operator menambahkan `--delete` secara eksplisit.

Export XLSX memakai direktori privat terkontrol `REPORT_TEMPORARY_DIRECTORY`. Cleanup langsung selalu dicoba setelah export, sedangkan file yang tertinggal dipindai setiap jam dan dihapus setelah `REPORT_TEMPORARY_FILE_GRACE_MINUTES` oleh queue worker.
