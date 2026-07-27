<?php

namespace Tests\Feature;

use App\Models\Permission;
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
            ->assertSee('Cari nama, email, atau telepon')
            ->assertSee('Memuat data produk')
            ->assertSee('Muat ulang produk')
            ->assertSee('Subtotal item')
            ->assertSee('Pajak & diskon', escape: false)
            ->assertSee('Kalkulasi diperbarui otomatis')
            ->assertSee('Total tagihan')
            ->assertSee('INV-2026-0079')
            ->assertSee('save-invoice-draft')
            ->assertSee('invoice-validation-summary')
            ->assertSee('data-validation-field="due_date"', escape: false)
            ->assertSee('data-validation-field="items"', escape: false)
            ->assertSee('Draft tersimpan via API')
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
        $this->get('/invoices')
            ->assertOk()
            ->assertSee('Daftar Invoice - YokPrinting.ID')
            ->assertSee('Daftar Invoice')
            ->assertSee('Semua invoice')
            ->assertSee('Cari invoice atau pelanggan')
            ->assertSee('INV-2026-0084')
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('INV-2026-0078')
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
            ->assertSee('name="email"', escape: false)
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
            ->assertSee('Workspace setelah login')
            ->assertSee('Sesi aktif')
            ->assertSee('Navigasi dasar sudah siap')
            ->assertSee('Role aktif')
            ->assertSee('Pemilik usaha')
            ->assertSee('logout-placeholder-button')
            ->assertSee(route('logout'))
            ->assertSee(route('roles.index'))
            ->assertSee(route('activity-logs.index'))
            ->assertSee('Ringkasan keuangan')
            ->assertSee('Pendapatan bulan ini')
            ->assertSee('Tren pendapatan')
            ->assertSee('due-notification-card')
            ->assertSee('Notifikasi jatuh tempo')
            ->assertSee('Invoice perlu ditindaklanjuti')
            ->assertSee('Lewat tempo 3 hari')
            ->assertSee('Jatuh tempo besok')
            ->assertSee('Kirim pengingat')
            ->assertSee(route('payments.receivables.index'))
            ->assertSee(route('notifications.due-invoices.index'))
            ->assertSee('Ringkasan stok menipis')
            ->assertSee('Cup 16 Oz Oval 8gr')
            ->assertSee('Antrean produksi sablon cup')
            ->assertSee('Minimum stok')
            ->assertSee(route('products.index'))
            ->assertSee('Aktivitas terbaru')
            ->assertSee('Invoice yang perlu dipantau')
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('INV-2026-0084')
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
        $this->get('/notifications/due-invoices')
            ->assertOk()
            ->assertSee('Invoice Jatuh Tempo - YokPrinting.ID')
            ->assertSee('Daftar invoice jatuh tempo')
            ->assertSee('Antrian follow-up invoice')
            ->assertSee('Total perlu follow-up')
            ->assertSee('Nilai outstanding')
            ->assertSee('INV-2026-0078')
            ->assertSee('PT Bumi Lestari')
            ->assertSee('Lewat 3 hari')
            ->assertSee('Kirim reminder massal')
            ->assertSee('due-invoice-search')
            ->assertSee('due-invoice-status-filter')
            ->assertSee('due-invoice-owner-filter')
            ->assertSee('Tandai follow-up')
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
        $this->get('/customers')
            ->assertOk()
            ->assertSee('Indeks pelanggan')
            ->assertSee('Total pelanggan')
            ->assertSee('Pelanggan aktif')
            ->assertSee('Tabel pelanggan')
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('CV Lautan Rasa')
            ->assertSee('Rp74.850.000')
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
            ->assertSee('Backend penyimpanan akan menyusul')
            ->assertSee(route('customers.index'));
    }

    public function test_customer_edit_form_page_is_available(): void
    {
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
        $this->get('/customers/CUS-001')
            ->assertOk()
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('Detail pelanggan')
            ->assertSee('Riwayat invoice')
            ->assertSee('Pembayaran terakhir')
            ->assertSee('Timeline aktivitas')
            ->assertSee('INV-2026-0084')
            ->assertSee('BCA-77219')
            ->assertSee('Rp42.850.000')
            ->assertSee(route('customers.edit', ['customer' => 'CUS-001']))
            ->assertSee(route('customers.index'));
    }

    public function test_products_index_page_is_available(): void
    {
        $this->get('/products')
            ->assertOk()
            ->assertSee('Daftar produk')
            ->assertSee('Total produk')
            ->assertSee('Stok menipis')
            ->assertSee('Tabel produk')
            ->assertSee('Paket desain brand refresh')
            ->assertSee('Cetak katalog premium')
            ->assertSee('PRM-FLYER-01')
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
            ->assertSee('/products/${product.sku}/edit', escape: false)
            ->assertSee(route('products.index'));
    }

    public function test_product_create_form_page_is_available(): void
    {
        $this->get('/products/create')
            ->assertOk()
            ->assertSee('Tambah produk baru')
            ->assertSee('Informasi produk')
            ->assertSee('Harga beli & stok', escape: false)
            ->assertSee('Satuan master produk dikunci ke PCS')
            ->assertSee('Preview katalog')
            ->assertSee('productForm')
            ->assertSee('product-validation-summary')
            ->assertSee('product-saved-notice')
            ->assertSee('Simpan produk')
            ->assertSee('Backend penyimpanan produk akan menyusul')
            ->assertSee(route('products.index'));
    }

    public function test_product_edit_form_page_is_available(): void
    {
        $this->get('/products/PRN-CATALOG-01/edit')
            ->assertOk()
            ->assertSee('Edit produk PRN-CATALOG-01')
            ->assertSee('Cetak katalog premium')
            ->assertSee('4200000')
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
        $this->get('/payments/receivables')
            ->assertOk()
            ->assertSee('Daftar piutang')
            ->assertSee('Total piutang')
            ->assertSee('Tabel piutang')
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('CV Lautan Rasa')
            ->assertSee('Rp74.850.000')
            ->assertSee('Overdue')
            ->assertSee('receivablesTable')
            ->assertSee('Filter status piutang')
            ->assertSee('sortBy')
            ->assertSee('x-model.debounce.150ms="query"', escape: false);
    }

    public function test_payment_history_page_is_available(): void
    {
        $this->get('/payments/history')
            ->assertOk()
            ->assertSee('Riwayat pembayaran')
            ->assertSee('Tabel riwayat pembayaran')
            ->assertSee('Pembayaran diterima')
            ->assertSee('BCA-77302')
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('Terverifikasi')
            ->assertSee('Menunggu')
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
            ->assertSee('Total penjualan')
            ->assertSee('Tabel penjualan')
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('Komposisi produk')
            ->assertSee('Rp312.400.000')
            ->assertSee('Catatan analisis')
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
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('Sablon Cup 16 Oz Oval')
            ->assertSee('Rp19.980.000')
            ->assertSee('Minimal DP 50%')
            ->assertSee('Kembali ke editor')
            ->assertSee('Simpan draft')
            ->assertSee('Kirim email')
            ->assertSee('Unduh PDF')
            ->assertSee('preview-action-notice')
            ->assertSee('invoicePreviewActions')
            ->assertSee('finance@sinarnusantara.co.id');
    }
}
