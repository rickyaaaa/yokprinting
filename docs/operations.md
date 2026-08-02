# Operasional YokPrinting

Lifecycle bukti Pengeluaran membutuhkan dua proses aplikasi yang berjalan terus-menerus:

- Laravel scheduler: jalankan `php artisan schedule:work` saat development, atau cron `php artisan schedule:run` setiap menit saat deployment.
- Laravel queue worker: jalankan `php artisan queue:work` karena purge, retry cleanup, dan heartbeat worker memakai queue.

Gunakan `php artisan operations:health` untuk memeriksa heartbeat scheduler dan worker. Command keluar dengan status gagal bila salah satunya belum pernah aktif atau melewati `OPERATIONS_HEALTH_MAX_AGE_MINUTES`. Cache harus dibagi antar-instance jika aplikasi dijalankan pada lebih dari satu instance.

Gunakan `php artisan expenses:proofs:scan` untuk dry-run pencarian bukti yatim. Grace period default dikonfigurasi melalui `EXPENSE_PROOF_ORPHAN_GRACE_MINUTES`; penghapusan hanya terjadi bila operator menambahkan `--delete` secara eksplisit.
