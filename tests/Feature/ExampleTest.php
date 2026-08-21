<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_invoice_create_page_is_available(): void
    {
        $response = $this->get('/invoices/create');

        $response
            ->assertOk()
            ->assertSee('Buat invoice baru')
            ->assertSee('Memuat pelanggan')
            ->assertSee('Muat ulang pelanggan')
            ->assertSee('Cari kode, nama, email, atau telepon')
            ->assertSee('Memuat data produk')
            ->assertSee('Muat ulang produk')
            ->assertSee('Tinta')
            ->assertSee('Jenis cetak')
            ->assertDontSee('Ukuran')
            ->assertDontSee('Gramasi')
            ->assertDontSee('Kelipatan jumlah')
            ->assertSee('Subtotal item')
            ->assertSee('Pajak & diskon', escape: false)
            ->assertSee('Kalkulasi diperbarui otomatis')
            ->assertSee('Total tagihan')
            ->assertSee('Dibuat otomatis saat disimpan')
            ->assertSee('save-invoice-draft')
            ->assertSee('invoice-preview-requested')
            ->assertSee('invoice-validation-summary')
            ->assertSee('data-validation-field="due_date"', escape: false)
            ->assertSee('data-validation-field="items"', escape: false)
            ->assertSee('Invoice tersimpan via API')
            ->assertSee('Pratinjau invoice')
            ->assertSee(route('invoices.preview'))
            ->assertSee('Peran &amp; akses', escape: false)
            ->assertSee('Log aktivitas')
            ->assertSee('Pengaturan')
            ->assertSee(route('invoices.index'))
            ->assertSee(route('roles.index'))
            ->assertSee(route('activity-logs.index'))
            ->assertSee(route('settings.company-profile.edit'));
    }

    public function test_invoice_index_page_is_available(): void
    {
        $customer = Customer::query()->create([
            'name' => 'PT Invoice Nyata',
            'email' => 'invoice-nyata@example.test',
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0099',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-07-20',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 5600000,
        ]);

        $this->get('/invoices')
            ->assertOk()
            ->assertSee('Daftar Invoice - YokPrinting.ID')
            ->assertSee('Daftar Invoice')
            ->assertSee('Semua invoice')
            ->assertSee('Cari invoice atau pelanggan')
            ->assertSee('INV-2026-0099')
            ->assertSee('PT Invoice Nyata')
            ->assertSee('Overdue')
            ->assertSee(route('invoices.create'))
            ->assertSee('/payments/invoices/${invoice.number}', escape: false);
    }

    public function test_home_redirects_to_dashboard_page(): void
    {
        $this->get('/')
            ->assertRedirect(route('dashboard'));
    }

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Login - YokPrinting.ID')
            ->assertSee('Welcome Back')
            ->assertSee('Sign in to YokPrinting.ID')
            ->assertSee('Username')
            ->assertSee('name="username"', escape: false)
            ->assertSee('autocomplete="username"', escape: false)
            ->assertSee('name="password"', escape: false)
            ->assertSee('name="remember"', escape: false)
            ->assertSee('toggle-password-visibility')
            ->assertSee('Sign In')
            ->assertDontSee('Forgot Password?')
            ->assertSee('data:image/png;base64,')
            ->assertDontSee('Butuh akses baru?')
            ->assertDontSee('Daftarkan akun perusahaan')
            ->assertDontSee('/register');
    }

    public function test_public_register_page_redirects_to_login(): void
    {
        $this->get('/register')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Registrasi publik dinonaktifkan. Akun baru hanya dapat dibuat dari dashboard admin.');
    }

    public function test_dashboard_page_is_available(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('post-login-home-shell')
            ->assertDontSee('Workspace setelah login')
            ->assertDontSee('Halo, Andi. Navigasi dasar sudah siap.')
            ->assertDontSee('logout-placeholder-button')
            ->assertSee(route('roles.index'))
            ->assertSee(route('activity-logs.index'))
            ->assertSee('Ringkasan keuangan')
            ->assertSee('Pendapatan bulan ini')
            ->assertSee('Tren pendapatan')
            ->assertSee('Notifikasi jatuh tempo')
            ->assertSee('notificationBell')
            ->assertSee(route('payments.receivables.index'))
            ->assertSee(route('notifications.due-invoices.index'))
            ->assertSee('Ringkasan stok menipis')
            ->assertSee('Antrean produksi sablon cup')
            ->assertSee(route('products.index'))
            ->assertSee('Aktivitas terbaru')
            ->assertSee('Invoice yang perlu dipantau')
            ->assertDontSee('PT Sinar Nusantara')
            ->assertDontSee('INV-2026-0084')
            ->assertSee(route('settings.company-profile.edit'))
            ->assertSee(route('invoices.index'))
            ->assertSee(route('invoices.create'));
    }

    public function test_roles_index_page_is_available(): void
    {
        $this->get('/roles')
            ->assertOk()
            ->assertSee('Peran & Akses - YokPrinting.ID', escape: false)
            ->assertSee('Daftar peran')
            ->assertSee('Matrix permission')
            ->assertSee('Owner')
            ->assertSee('Admin Finance')
            ->assertSee('finance_admin')
            ->assertSee('Sales')
            ->assertSee('Viewer')
            ->assertSee('Tambah peran')
            ->assertSee('create-role-placeholder')
            ->assertSee('role-based access control')
            ->assertSee(route('roles.create'))
            ->assertSee(route('roles.edit', 'finance_admin'))
            ->assertSee(route('roles.permissions.edit', 'finance_admin'))
            ->assertSee(route('dashboard'));
    }

    public function test_due_invoice_notification_list_page_is_available(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0301',
            'issue_date' => now()->subDays(17)->toDateString(),
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 5600000,
        ]);

        $this->get('/notifications/due-invoices')
            ->assertOk()
            ->assertSee('Invoice Jatuh Tempo - YokPrinting.ID')
            ->assertSee('Daftar invoice jatuh tempo')
            ->assertSee('Antrian follow-up invoice')
            ->assertSee('Total perlu follow-up')
            ->assertSee('Nilai outstanding')
            ->assertSee('INV-2026-0301')
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('Lewat 3 hari')
            ->assertSee('due-invoice-search')
            ->assertSee('due-invoice-status-filter')
            ->assertSee(route('payments.receivables.index'))
            ->assertSee(route('dashboard'));
    }

    public function test_activity_logs_page_is_available(): void
    {
        $this->get('/activity-logs')
            ->assertOk()
            ->assertSee('Log Aktivitas - YokPrinting.ID')
            ->assertSee('Log aktivitas')
            ->assertSee('Filter log aktivitas')
            ->assertSee('Total event hari ini')
            ->assertSee('Risiko tinggi')
            ->assertSee('Percobaan login gagal')
            ->assertSee('Mengubah permission Admin Finance')
            ->assertSee('activity-log-search')
            ->assertSee('activity-type-filter')
            ->assertSee('activity-module-filter')
            ->assertSee('activity-date-from')
            ->assertSee('export-activity-log-placeholder')
            ->assertSee('id="app-sidebar"', false)
            ->assertSee('Buka navigasi')
            ->assertSee(route('roles.index'))
            ->assertSee(route('dashboard'));
    }

    public function test_role_create_form_page_is_available(): void
    {
        $this->get('/roles/create')
            ->assertOk()
            ->assertSee('Tambah Peran - YokPrinting.ID')
            ->assertSee('Tambah peran baru')
            ->assertSee('Informasi peran')
            ->assertSee('Permission modul')
            ->assertSee('name="name"', escape: false)
            ->assertSee('name="code"', escape: false)
            ->assertSee('name="permissions[]"', escape: false)
            ->assertSee('permission-dashboard')
            ->assertSee('permission-role')
            ->assertSee('Simpan peran baru')
            ->assertSee('role-form-saved-notice')
            ->assertSee(route('roles.index'));
    }

    public function test_role_edit_form_page_is_available(): void
    {
        $this->get('/roles/finance_admin/edit')
            ->assertOk()
            ->assertSee('Edit Peran - YokPrinting.ID')
            ->assertSee('Edit peran')
            ->assertSee('Admin Finance')
            ->assertSee('finance_admin')
            ->assertSee('Finance &amp; laporan', escape: false)
            ->assertSee('Simpan perubahan peran')
            ->assertSee('Mode edit')
            ->assertSee(route('roles.permissions.edit', 'finance_admin'))
            ->assertSee(route('roles.index'));
    }

    public function test_role_permissions_page_is_available(): void
    {
        $this->get('/roles/finance_admin/permissions')
            ->assertOk()
            ->assertSee('Izin Admin Finance - YokPrinting.ID')
            ->assertSee('Pengaturan izin Admin Finance')
            ->assertSee('Matrix izin per modul')
            ->assertSee('Risiko perubahan')
            ->assertSee('Sedang')
            ->assertSee('permission-invoice.view')
            ->assertSee('permission-payment.export')
            ->assertSee('permission-role.delete')
            ->assertSee('Simpan izin')
            ->assertSee('permission-saved-notice')
            ->assertSee(route('roles.edit', 'finance_admin'))
            ->assertSee(route('roles.index'));
    }

    public function test_role_permissions_page_uses_persisted_database_permissions(): void
    {
        $role = Role::query()->create([
            'name' => 'Owner',
            'code' => Role::CODE_OWNER,
            'is_system' => true,
        ]);
        $dashboardView = Permission::query()->create([
            'name' => 'Lihat Dashboard',
            'code' => 'dashboard.view',
            'module' => 'dashboard',
            'action' => 'view',
        ]);
        Permission::query()->create([
            'name' => 'Hapus Role',
            'code' => 'role.delete',
            'module' => 'role',
            'action' => 'delete',
        ]);
        $role->permissions()->sync([$dashboardView->id]);

        $html = $this->get('/roles/owner/permissions')
            ->assertOk()
            ->content();

        $this->assertMatchesRegularExpression('/data-testid="permission-dashboard\.view"[\s\S]{0,260}checked|checked[\s\S]{0,260}data-testid="permission-dashboard\.view"/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-testid="permission-role\.delete"[\s\S]{0,260}checked|checked[\s\S]{0,260}data-testid="permission-role\.delete"/', $html);
    }

    public function test_customers_index_page_is_available(): void
    {
        Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
            'phone' => '+62 21 555 0198',
            'address' => 'Jl. Sudirman No. 1',
            'city' => 'Jakarta Selatan',
        ]);
        Customer::query()->create([
            'code' => 'CUS-002',
            'name' => 'CV Lautan Rasa',
            'email' => 'billing@lautanrasa.example',
            'phone' => '+62 361 700 210',
            'address' => 'Jl. Pantai No. 2',
            'city' => 'Denpasar',
        ]);

        $this->get('/customers')
            ->assertOk()
            ->assertSee('Indeks pelanggan')
            ->assertSee('Total pelanggan')
            ->assertSee('Pelanggan aktif')
            ->assertSee('Tabel pelanggan')
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('CV Lautan Rasa')
            ->assertSee('Tersimpan di database')
            ->assertSee('Filter status pelanggan')
            ->assertSee('customerIndexTable')
            ->assertSee('resultSummary')
            ->assertSee('setStatusFilter(filter.key)')
            ->assertSee('segmentFilter')
            ->assertSee('x-model.debounce.150ms="query"', escape: false)
            ->assertSee('Tambah pelanggan')
            ->assertSee(route('customers.create'))
            ->assertSee('/customers/${customer.code}', escape: false)
            ->assertSee(route('customers.index'));
    }

    public function test_customer_create_form_page_is_available(): void
    {
        $this->get('/customers/create')
            ->assertOk()
            ->assertSee('Tambah pelanggan baru')
            ->assertSee('Identitas pelanggan')
            ->assertSee('Alamat penagihan')
            ->assertSee('Preview kartu pelanggan')
            ->assertSee('customerForm')
            ->assertSee('customer-validation-summary')
            ->assertSee('customer-saved-notice')
            ->assertSee('Simpan pelanggan')
            ->assertSee('Kode pelanggan dibuat otomatis')
            ->assertDontSee('Kode pelanggan</span>', escape: false)
            ->assertDontSee('Segmen</span>', escape: false)
            ->assertSee(route('customers.index'));
    }

    public function test_customer_edit_form_page_is_available(): void
    {
        Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);

        $this->get('/customers/CUS-001/edit')
            ->assertOk()
            ->assertSee('Edit pelanggan CUS-001')
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('finance@sinarnusantara.co.id')
            ->assertSee('Simpan perubahan')
            ->assertSee('customerForm')
            ->assertSee(route('customers.index'));
    }

    public function test_customer_detail_history_page_is_available(): void
    {
        Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);

        $this->get('/customers/CUS-001')
            ->assertOk()
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('Detail pelanggan')
            ->assertSee('Riwayat invoice')
            ->assertSee('Pembayaran terakhir')
            ->assertSee('Timeline aktivitas')
            ->assertSee('Rp0')
            ->assertDontSee('INV-2026-0084')
            ->assertDontSee('BCA-77219')
            ->assertSee(route('customers.edit', ['customer' => 'CUS-001']))
            ->assertSee(route('customers.index'));
    }

    public function test_products_index_page_is_available(): void
    {
        Product::query()->create([
            'sku' => 'PRM-FLOW-01',
            'name' => 'Produk Demo Database',
            'purchase_price' => 4900000,
            'stock' => 6,
            'minimum_stock' => 12,
            'track_stock' => true,
        ]);

        $this->get('/products')
            ->assertOk()
            ->assertSee('Daftar produk')
            ->assertSee('Total produk')
            ->assertSee('Stok menipis')
            ->assertSee('Tabel produk')
            ->assertSee('Produk Demo Database')
            ->assertSee('PRM-FLOW-01')
            ->assertSee('Filter status produk')
            ->assertSee('productIndexTable')
            ->assertSee('resultSummary')
            ->assertSee('setStatusFilter(filter.key)')
            ->assertSee('categoryFilter')
            ->assertSee('Penanda stok menipis aktif')
            ->assertSee('lowStockProducts')
            ->assertSee('isLowStock(product)')
            ->assertSee('Minimum')
            ->assertSee('Di bawah minimum')
            ->assertSee('x-model.debounce.150ms="query"', escape: false)
            ->assertSee('Bulk edit stok')
            ->assertSee('Tambah produk')
            ->assertSee(route('products.create'))
            ->assertSee('/products/${product.id}/edit', escape: false)
            ->assertSee('deleteProduct(product)', escape: false)
            ->assertSee(route('products.index'));
    }

    public function test_product_create_form_page_is_available(): void
    {
        $this->get('/products/create')
            ->assertOk()
            ->assertSee('Tambah produk baru')
            ->assertSee('Informasi produk')
            ->assertSee('Informasi biaya & stok', escape: false)
            ->assertSee('Satuan master produk dikunci ke Pcs')
            ->assertSee('Preview katalog')
            ->assertSee('productForm')
            ->assertSee('product-validation-summary')
            ->assertSee('product-saved-notice')
            ->assertSee('Simpan produk')
            ->assertSee('Harga jual tidak disimpan di master produk')
            ->assertSee(route('products.index'));
    }

    public function test_product_edit_form_page_is_available(): void
    {
        $this->get('/products/1/edit')
            ->assertOk()
            ->assertSee('Edit produk')
            ->assertSee('Memuat detail produk')
            ->assertSee('Kosongkan untuk auto H-XXX')
            ->assertSee('Simpan perubahan')
            ->assertSee('productForm')
            ->assertSee('Perubahan produk tersimpan')
            ->assertSee(route('products.index'));
    }

    public function test_company_profile_settings_page_is_available(): void
    {
        $this->get('/settings/company-profile')
            ->assertOk()
            ->assertSee('Pengaturan profil usaha')
            ->assertSee('Navigasi pengaturan profil usaha')
            ->assertSee('#company-info')
            ->assertSee('#default-invoice-settings')
            ->assertSee('#brand-assets')
            ->assertSee('#settings-preview')
            ->assertSee('Formulir informasi perusahaan')
            ->assertSee('Identitas usaha')
            ->assertSee('Jenis badan usaha')
            ->assertSee('NIB-9120310045517')
            ->assertSee('Bidang usaha')
            ->assertSee('Andi Pratama')
            ->assertSee('Alamat & pembayaran', escape: false)
            ->assertSee('Pengaturan default invoice')
            ->assertSee('Template invoice default')
            ->assertSee('Professional clean')
            ->assertSee('Modern compact')
            ->assertSee('PPN default')
            ->assertSee('Jatuh tempo default')
            ->assertSee('currentInvoiceTemplate')
            ->assertSee('company-profile-validation-summary')
            ->assertSee('validationMessages')
            ->assertSee('Tersimpan pukul')
            ->assertSee('data-validation-field="defaultTaxRate"', escape: false)
            ->assertSee('Upload logo perusahaan')
            ->assertSee('Pratinjau logo perusahaan')
            ->assertSee('company-logo-input')
            ->assertSee('handleLogoUpload')
            ->assertSee('Hapus logo')
            ->assertSee('Palet warna tema invoice')
            ->assertSee('Sage profesional')
            ->assertSee('Ocean blue')
            ->assertSee('selectPalette')
            ->assertSee('currentPalette')
            ->assertSee('Pratinjau langsung warna tema')
            ->assertSee('theme-live-preview')
            ->assertSee('themeLivePreviewStyle')
            ->assertSee('Preview profil invoice')
            ->assertSee('Ruang Karya Digital')
            ->assertSee('09.876.543.2-101.000')
            ->assertSee('companyProfileSettings')
            ->assertSee('company-profile-saved-notice')
            ->assertSee('Simpan profil usaha')
            ->assertSee(route('dashboard'));
    }

    public function test_payment_invoice_detail_page_is_available(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.example',
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0084',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-07-30',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'production_status' => Invoice::PRODUCTION_DESIGN_ACC,
            'currency' => 'IDR',
            'subtotal' => 18450000,
            'total_amount' => 18450000,
        ]);

        $this->get('/payments/invoices/INV-2026-0084')
            ->assertOk()
            ->assertSee('Detail INV-2026-0084')
            ->assertSee('Manajemen pembayaran')
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('Rincian tagihan')
            ->assertSee('Pembayaran')
            ->assertSee('Sisa tagihan')
            ->assertSee('Riwayat pembayaran')
            ->assertSee('Instruksi pembayaran')
            ->assertSee('Catat pembayaran')
            ->assertSee('payment-validation-summary')
            ->assertSee('payment-saved-notice')
            ->assertSee('Simpan pembayaran')
            ->assertSee('Gunakan sisa');
    }

    public function test_receivables_page_is_available(): void
    {
        $customer = Customer::query()->create([
            'name' => 'PT Piutang Nyata',
            'email' => 'piutang@example.test',
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-PIUTANG-001',
            'issue_date' => today()->subDays(14),
            'due_date' => today()->subDay(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 74850000,
        ]);

        $this->get('/payments/receivables')
            ->assertOk()
            ->assertSee('Daftar piutang')
            ->assertSee('Total piutang')
            ->assertSee('Tabel piutang')
            ->assertSee('PT Piutang Nyata')
            ->assertSee('INV-PIUTANG-001')
            ->assertSee('Rp74.850.000')
            ->assertSee('Overdue')
            ->assertSee('receivablesTable')
            ->assertSee('Filter status piutang')
            ->assertSee('sortBy')
            ->assertSee('x-model.debounce.150ms="query"', escape: false);
    }

    public function test_payment_history_page_is_available(): void
    {
        $customer = Customer::query()->create([
            'name' => 'PT Pembayaran Nyata',
            'email' => 'pembayaran@example.test',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-BAYAR-001',
            'issue_date' => today(),
            'due_date' => today()->addWeek(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'total_amount' => 10000000,
        ]);
        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-DEMO-001',
            'payment_date' => today(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'reference' => 'BCA-NYATA-001',
            'amount' => 4000000,
            'status' => Payment::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $this->get('/payments/history')
            ->assertOk()
            ->assertSee('Riwayat pembayaran')
            ->assertSee('Tabel riwayat pembayaran')
            ->assertSee('Pembayaran diterima')
            ->assertSee('BCA-NYATA-001')
            ->assertSee('PT Pembayaran Nyata')
            ->assertSee('Terverifikasi')
            ->assertSee('paymentHistoryTable')
            ->assertSee('Filter status riwayat pembayaran')
            ->assertSee('x-model.debounce.150ms="query"', escape: false)
            ->assertSee(route('payments.receivables.index'));
    }

    public function test_sales_report_page_is_available(): void
    {
        $this->get('/reports/sales')
            ->assertOk()
            ->assertSee('Ringkasan penjualan')
            ->assertSee('Tabel penjualan')
            ->assertSee('Komposisi produk')
            ->assertSee('Catatan analisis')
            ->assertSee('summaryCards')
            ->assertSee('filteredSales')
            ->assertSee('salesReportChart')
            ->assertSee('x-ref="salesChart"', escape: false)
            ->assertSee('exportExcel')
            ->assertSee('Export Excel')
            ->assertSee('Laporan penjualan berhasil diunduh');
    }

    public function test_invoice_preview_page_is_available(): void
    {
        $this->get('/invoices/preview')
            ->assertOk()
            ->assertSee('YokPrinting.ID')
            ->assertSee('Jl. Karyawan II')
            ->assertSee('Pelanggan belum dipilih')
            ->assertSee('x-for="item in preview.items"', escape: false)
            ->assertSee('formatCurrency(preview.total_amount)', escape: false)
            ->assertSee('Rp0')
            ->assertSee('Minimal DP')
            ->assertSee('preview.dp_required_percent')
            ->assertDontSee('Sablon Cup 16 Oz Oval')
            ->assertDontSee('Rp19.980.000')
            ->assertSee('Kembali ke editor')
            ->assertSee('Simpan invoice')
            ->assertSee('Kirim via WA')
            ->assertSee('Unduh PDF')
            ->assertSee('preview-action-notice')
            ->assertSee('invoicePreviewActions')
            ->assertSee('!canSendWhatsApp')
            ->assertDontSee('finance@sinarnusantara.co.id');
    }
}
