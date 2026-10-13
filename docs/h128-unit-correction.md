# H-128: audit dan dry-run koreksi satuan — 13 Oktober 2026

## Kesimpulan

Masalah satuan invoice cukup diperbaiki lewat koreksi master resmi, bukan dengan
hardcode frontend. Default baris kosong adalah Pcs; setelah item dipilih,
`invoiceProductDefaults` / `selectProduct` / `updateProduct` memakai `product.unit`.
`SnapshotInvoiceItems` juga memakai satuan master. Production mengirim Pekerjaan
karena master H-128 memang masih Pekerjaan.

Ada patch backend lokal kecil yang diperlukan untuk memenuhi syarat audit:
`ProductController::update` sebelumnya hanya mencatat kategori/jenis, bukan
satuan. Kini perubahan unit dicatat sebagai `product / unit_updated`, dengan
actor, subject, route, SKU, serta unit sebelum/sesudah. Log dan update berada
dalam transaksi yang sama. Save tanpa perubahan unit tidak membuat log duplikat.

Tidak ada perubahan logika satuan frontend atau pemaksaan semua Jasa ke Pcs.
Jasa lama dengan unit Paket/Jam/Pekerjaan tetap kompatibel.

## Audit production read-only

Audit pada 13 Oktober 2026 sekitar **17:02 WIB**, release **2e54894**:

| Field | Nilai |
| --- | --- |
| ID / SKU / nama | 130 / H-128 / Jasa Cetak |
| product_type | service |
| unit | Pekerjaan |
| packaging_unit legacy | pcs |
| track_stock / stock | false / null |
| minimum_order_qty / package_conversion | 1 / 1 |
| moq_quantity / order_increment | 1 / 1 |
| purchase_price | Rp0 |
| average_purchase_cost / last_purchase_price | null / null |
| effective cost fallback | Rp0 |
| inventory batch / stock movement | 0 / 0 |
| supplier price rows | 0 |
| invoice item aktif maupun soft-deleted memakai H-128 | 0 |
| updated_at UTC | 2026-10-13T07:42:51Z |

Authenticated GET di runtime aplikasi production:

- `/api/products/130`: HTTP 200, unit `Pekerjaan`, track_stock false.
- `/api/products/options?product_type=service&search=H-128`: HTTP 200, unit
  `Pekerjaan`, package_conversion 1.

Tidak ada tarif jual default yang dikirim picker; form memulai harga dari 0
dan admin mengisi harga per item invoice. Angka Rp200 pada acceptance test adalah
contoh, bukan persetujuan tarif production. Tidak ada biaya nonzero master yang
perlu dikonversi berdasarkan snapshot audit ini. Jangan menganggap hal tersebut
tetap benar bila master berubah sebelum eksekusi.

## Dry-run target yang memerlukan persetujuan

Normalisasi payload di runtime production, **tanpa update/save**, menghasilkan
hanya satu perubahan bisnis:

```json
{
  "read_only": true,
  "target": { "id": 130, "sku": "H-128" },
  "endpoint": "PATCH /api/products/130",
  "payload": { "unit": "Pcs" },
  "changes": { "unit": { "before": "Pekerjaan", "after": "Pcs" } }
}
```

Efek operasional setelah disetujui: updated_at berubah, serta satu audit log
`unit_updated` ditambahkan melalui patch API. Harga/biaya, jenis, kategori,
pelacakan stok dan minimum/kelipatan tidak diubah. `packaging_unit` master
legacy tetap `pcs`; snapshot invoice baru mengambil `unit` dan `packaging_unit`
dari master `unit=Pcs`. Invoice lama tidak disentuh.

## Urutan eksekusi aman — belum dijalankan

1. Minta persetujuan terpisah untuk deploy patch audit dan koreksi **hanya**
   ID 130 / H-128 dari Pekerjaan ke Pcs; tidak ada bulk conversion.
2. Verifikasi ulang identitas, updated_at, biaya dan referensi invoice. Jika
   ada perubahan sejak audit ini, batalkan eksekusi otomatis dan review ulang.
3. Pastikan backup sebelum koreksi masih relevan; buat backup baru bila ada
   transaksi/perubahan baru. Backup harus berada di luar document root, dengan
   direktori 700 dan file 600, dump DB exit 0, gzip/tar valid.
4. Deploy patch audit yang telah diuji. Jangan menjalankan reset/seeder/backfill.
5. Gunakan form edit produk atau authenticated API resmi dengan payload di atas.
   Jangan memakai SQL UPDATE, tinker save, atau mempercayai unit dari invoice
   payload. Pastikan audit actor dan before/after tercatat.
6. GET detail/options harus sama-sama Pcs. Pada invoice baru, pilih Jasa lalu
   H-128 dan pastikan jumlah Pcs, harga per Pcs, kelipatan 1, Tinta/Jenis cetak
   tersedia. Masukkan tarif jual per pcs yang disetujui client.
7. Verifikasi data historis dan inventory tidak berubah. UAT transaksi nyata
   membutuhkan izin; jangan membuat dummy invoice/payment production.

## Backup yang disiapkan

Backup aplikasi dan database production dibuat sekitar **17:08 WIB** tanpa
mengubah master/transaksi:

`/home/u433850416/backups/yokprinting/pre-h128-unit-20261013-F14uRZ`

- `application.tar.gz`: SHA-256
  `94756bfd1cdf5dff36438f38e50d77f9fade7327db2b6d0b23a9ab63198ab722`.
- `database.sql.gz`: SHA-256
  `3eebfa44018904d8b153491a4140068835606b625991b8909a9c071571d0a15d`.
- Dump exit 0, 40 CREATE TABLE, marker dump completed ditemukan, pemeriksaan
  gzip dan tar berhasil. Folder 700, file 600, di luar document root.
- Verifikasi setelah backup: unit masih Pekerjaan, updated_at master tetap
  2026-10-13T14:42:51+07:00, invoice item memakai H-128 tetap 0.
- Ini bukan backup untuk dipulihkan sembarang saat: restore seluruh database
  akan menimpa transaksi baru. Koreksi satuan harus dibalik secara terarah melalui
  alur edit resmi jika diperlukan, bukan otomatis restore database penuh.

## Verifikasi lokal

- PHP: **701 test lulus, 4.623 assertion**.
- JavaScript: **38 test lulus**.
- Vite build berhasil, bundle tidak berubah dari release sebelumnya. Warning
  font existing `/fonts/dm-sans-latin-variable.woff2` tetap ada.
- Pint, `git diff --check`, dan syntax JS lulus.
- Smoke memakai metode component aktual `changeItemType`, `selectProduct`,
  `updateProduct`, dan `restoreItems`, bukan hanya mengecek default sebelum klik.
- Koreksi unit resmi → API/options → invoice baru → reload/edit → PDF/CSV diuji.
- Spoofed unit/type/stock pada payload tidak mengalahkan master.
- 1.000 Pcs × Rp200 = Rp200.000; Jasa tidak membuat cost layer/mutasi/batch.
- Invoice campuran per Pcs memotong stok Produk sekali saat DP, tidak lagi saat
  pelunasan/save ulang; Jasa tetap stock-neutral.
- Snapshot unit, quantity, harga, total dan HPP invoice lama tetap utuh setelah
  koreksi master serta save ulang item/quantity yang sama.
- Smoke ini otomatis pada component/controller/render PDF, bukan klaim klik
  browser interaktif atau visual UAT production setelah koreksi.

## File patch lokal

- `app/Http/Controllers/Api/ProductController.php`: audit perubahan satuan.
- `tests/Feature/ServicePrintingPerPcsTest.php`: koreksi resmi, audit/no-op,
  snapshot historis, invoice baru dan lifecycle campuran.
- `tests/js/product-type.test.mjs`: pemilihan/update/hydration master H-128 Pcs.
- Dokumen ini: audit, dry-run dan langkah koreksi.

**Belum deploy dan belum mengubah data production.**
