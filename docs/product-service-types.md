# Produk dan Jasa

`product_type` terpisah dari kategori katalog (`category_id`). UI memakai label
**Jenis Barang**, dengan pilihan **Produk** (`goods`) dan **Jasa** (`service`).

## Aturan transaksi

- Produk mengikuti pelacakan stok existing. Stok invoice baru dikonsumsi pada
  pembayaran pertama terverifikasi, bukan saat invoice dibuat atau diterbitkan.
- Jasa selalu `track_stock=false`, quantity/kelipatan mulai 1, dengan satuan
  penagihan sendiri. Jasa tetap masuk invoice, pembayaran, piutang, penjualan,
  serta dokumen/export; tidak masuk FIFO, mutasi stok, atau nilai persediaan.
- Invoice campuran didukung. Tidak ada paket/BOM atau konsumsi bahan tersembunyi.
- Biaya jasa memakai cost fallback existing dan disimpan pada snapshot; bukan
  otomatis dianggap nol. Form ini tidak menambahkan mekanisme biaya jasa baru.

## Kompatibilitas dan audit

Migration hanya menambah kolom nullable dan index; tidak melakukan backfill.
`product_type=null` berarti belum diklasifikasikan. Ditampilkan sementara sebagai
Produk untuk kompatibilitas, **tanpa mengaktifkan pelacakan stok**. Barang lama
non-stock tidak otomatis dianggap Jasa.

Setelah migration dijalankan pada environment yang dituju, audit read-only:

```sh
php artisan app:audit-product-types --json
```

Review daftar sebelum mapping. Koreksi dilakukan lewat edit produk resmi.
Perubahan jenis/pelacakan stok diblokir bila perilaku berubah dan ada stok,
inventory batch, stock movement, invoice item (termasuk histori soft-deleted),
atau purchase order item. Buat katalog baru bila jenis berbeda diperlukan untuk
produk yang sudah memiliki histori. Perubahan klasifikasi dicatat pada activity log.

Invoice baru menyimpan jenis/pelacakan stok dalam `invoice_items.metadata.inventory`
dan satuan dalam kolom `unit`. Invoice lama tanpa snapshot tetap memakai fallback
perilaku lama; guard master melindunginya. Save ulang dengan product/quantity sama
mempertahankan snapshot jenis, satuan dan biaya. Tidak ada rewrite invoice lama.

## Deployment

Perubahan ini memerlukan migration dan build Vite yang sesuai. Jangan hanya
mengirim JS baru tanpa backend/migration. Lakukan backup sebelum deployment
resmi dan jangan menjalankan seeder/reset atau mapping massal. Deploy dan mapping
production memerlukan persetujuan eksplisit terpisah.

## File implementasi

Model/schema dan guard:
- `database/migrations/2026_10_13_000000_add_product_type_to_products.php`
- `app/Models/Product.php`, `app/Models/InvoiceItem.php`
- `app/Services/Inventory/NormalizeProductType.php`
- `app/Console/Commands/AuditProductTypes.php`

API, validasi dan halaman:
- `app/Http/Controllers/Api/ProductController.php`
- `app/Http/Controllers/Api/ProductOptionController.php`
- `app/Http/Controllers/Api/ProductBulkStockController.php`
- `app/Http/Controllers/Api/InvoiceDraftController.php`
- `app/Http/Controllers/InvoiceEditPageController.php`
- `app/Http/Controllers/ProductIndexPageController.php`
- `app/Http/Controllers/StockMutationReportPageController.php`
- `app/Http/Requests/StoreProductRequest.php`, `UpdateProductRequest.php`
- `app/Http/Requests/ListProductsRequest.php`, `ListProductOptionsRequest.php`
- `app/Http/Requests/StoreInvoiceDraftRequest.php` (juga dipakai update)

Snapshot, perhitungan, inventory dan laporan:
- `app/Services/Invoices/SnapshotInvoiceItems.php`
- `app/Services/Invoices/CalculateInvoiceTotals.php`
- `app/Services/Invoices/CalculateInvoicePreview.php`
- `app/Services/Invoices/UpdateInvoiceDraft.php`
- `app/Services/Invoices/RecordInvoiceSaleMovements.php`
- `app/Services/Inventory/FifoInventoryService.php`
- `app/Services/Inventory/RecordStockMovement.php`
- `app/Services/Inventory/ApplyManualOpeningCost.php`
- `app/Services/Purchasing/PostGoodsReceipt.php`
- `app/Services/Reports/BuildStockMutationReport.php`

Frontend dan regression test:
- `resources/js/app.js`, `resources/js/support/product-type.js`
- `resources/views/products/form.blade.php`, `resources/views/products/index.blade.php`
- `resources/views/components/invoice-items.blade.php`
- `tests/Feature/ProductServiceInventoryTest.php`, `tests/Feature/ExampleTest.php`
- `tests/js/product-type.test.mjs`
- `public/build/manifest.json` dan asset hasil build baru

Perubahan kategori canonical dari pekerjaan sebelumnya tetap dipertahankan,
tidak dibatalkan oleh fitur ini.

## Verifikasi fitur Produk/Jasa awal

- PHP: 692 test lulus, 4.475 assertion.
- JavaScript: 34 test lulus.
- Smoke otomatis: render form produk/invoice, state form/picker aktual,
  hydration/draft restore, serta lifecycle invoice campuran.
- PDF invoice dan export katalog PDF/Excel mengembalikan file valid.
- Detector UI pada tiga template yang berubah: tidak ada finding.
- Visual browser interaktif belum diverifikasi; smoke di atas bukan klaim UAT browser.
- Tidak ada audit/query/koreksi production atau deploy dalam pekerjaan ini.

## Perbaikan Jasa printing per Pcs — 13 Oktober 2026

### Akar masalah

- Default master/form Jasa memakai `Pekerjaan`, bukan `Pcs`.
- Panel Tinta/Jenis cetak disembunyikan untuk Jasa; backend snapshot dan restore
  juga mengosongkan spesifikasinya.
- PDF bersama hanya mencetak nama item, sehingga spesifikasi yang sudah tersedia
  belum tampil. Kini detail printing ditampilkan khusus Jasa yang memiliki
  Tinta/Jenis cetak, tanpa menambah detail pada dokumen barang lama.
- Restore dapat memilih produk yang salah ketika SKU kedua item sama-sama kosong.
- Input harga memakai step Rp500, tidak cocok untuk contoh Rp200/pcs.

### Perilaku baru dan kompatibilitas

Master Jasa baru default `Pcs`, minimum/kelipatan tetap 1. Form memakai panel
Tinta/Jenis cetak bersama, harga per satuan, dan keterangan jasa opsional.
Cup size/model/gramasi tidak dibawa ke Jasa. Pergantian item mengganti harga dan
spesifikasi dengan default item baru, bukan mempertahankan nilai stale.

Spesifikasi tersimpan pada invoice item dan bertahan di API, edit/restore,
preview, PDF serta export pesanan. Biaya Jasa tetap memakai snapshot existing.
Jasa tidak menyentuh FIFO/batch/mutasi; invoice campuran hanya mengurangi stok
Produk setelah pembayaran pertama terverifikasi, tepat satu kali.

Satuan master existing tidak dikonversi. Invoice historis mempertahankan satuan,
quantity, harga dan HPP snapshot, termasuk setelah master satuan diperbarui dan
invoice yang sama disimpan ulang tanpa mengganti item/quantity.

### File patch ini (di atas implementasi Produk/Jasa awal)

- `app/Services/Inventory/NormalizeProductType.php`
- `app/Services/Invoices/SnapshotInvoiceItems.php`
- `app/Services/Invoices/BuildInvoiceDocument.php`
- `app/Models/InvoiceItem.php`
- `app/Http/Controllers/Api/InvoiceDraftController.php`
- `app/Http/Requests/GenerateInvoicePreviewPdfRequest.php`
- `app/Console/Commands/AuditProductTypes.php`
- `resources/js/app.js`, `resources/js/support/product-type.js`
- `resources/views/components/invoice-items.blade.php`
- `resources/views/products/form.blade.php`
- `resources/views/payments/invoice-detail.blade.php`
- `resources/views/pdf/invoices/preview.blade.php`
- `tests/Feature/ServicePrintingPerPcsTest.php`
- `tests/js/product-type.test.mjs`
- `public/build/manifest.json` dan asset build Vite
- Dokumen ini.

### Hasil verifikasi akhir

- Seluruh PHP: **698 test lulus, 4.557 assertion**.
- Seluruh JavaScript: **37 test lulus**.
- Build Vite: berhasil; ada warning existing font
  `/fonts/dm-sans-latin-variable.woff2` tidak ditemukan saat build (runtime URL
  dipertahankan, bukan kegagalan build).
- Smoke otomatis: form produk/Jasa, shared panel, state picker dan restore,
  invoice Jasa 1.000 Pcs × Rp200 = Rp200.000, invoice campuran, DP/pelunasan,
  save ulang, snapshot lama, serta endpoint PDF/CSV.
- PDF stored invoice dan preview mengembalikan `%PDF-`; HTML sumber PDF memuat
  spesifikasi printing. Ini bukan klaim inspeksi visual PDF/browser atau UAT.
- Formatter PHP lulus, `git diff --check` bersih, detector Impeccable pada empat
  template tidak menemukan finding. Impeccable digunakan untuk menyamakan
  kontrol dan memperjelas harga per satuan tanpa menyamakan perilaku inventory.

### Audit production read-only

Audit SELECT melalui bootstrap aplikasi pada **13 Oktober 2026, 16:07 WIB**:

- Produk nondeleted: **127**.
- Master eksplisit Jasa: **1**.
- Jasa dengan satuan selain Pcs: **1**.
- Target review: **ID 130 / H-128 / Jasa Cetak / Pekerjaan**.

Tidak ada UPDATE, konversi harga/satuan, reset data, atau deploy. Client perlu
menyetujui harga dan satuan penagihan untuk H-128 melalui edit master resmi,
atau memakai master Jasa per Pcs baru. Mengganti satuan saja tidak otomatis
menjadikan harga per pekerjaan valid sebagai harga per pcs. Snapshot invoice
lama tidak boleh direwrite. Perintah audit patch ini juga melaporkan
`service_non_pcs_count` dan `services_needing_unit_review` tanpa menulis data.

Patch siap untuk review/deploy setelah persetujuan terpisah. Tidak memerlukan
migration tambahan di atas fitur Produk/Jasa yang sudah terpasang.
