<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\InventoryBatch;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;
use ZipArchive;

/**
 * Client request: "tambahin tarikan/eksport data produk atau stok", then a
 * reference screenshot showing a formatted workbook - title block, filter
 * dropdowns on the header row, tidy columns. CSV cannot carry any of that, so
 * the product catalogue exports as XLSX and PDF; there is deliberately no CSV.
 */
class ProductCatalogExportApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_excel_export_is_a_real_workbook_with_the_agreed_columns(): void
    {
        $tracked = $this->product('H-001', 'Cup Injection 12Oz Datar', 'Cup Injection', [
            'track_stock' => true,
            'stock' => 1000,
            'minimum_stock' => 500,
        ]);
        $this->batch($tracked, qtyRemaining: 1000, unitCost: 500);

        $response = $this->get(route('api.products.export.excel'))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

        $this->assertStringContainsString(
            'attachment; filename="data-produk-',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringEndsWith('.xlsx"', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('PK', $response->getContent());

        $sheet = $this->worksheetXml($response->getContent());

        foreach (['SKU', 'Nama Produk', 'Kategori', 'Unit', 'HPP FIFO', 'Stok', 'Minimum Stok', 'Status', 'Nilai Persediaan'] as $header) {
            $this->assertStringContainsString($header, $sheet);
        }

        $this->assertStringContainsString('H-001', $sheet);
        $this->assertStringContainsString('Cup Injection 12Oz Datar', $sheet);
        // HPP FIFO 500, stok 1000, minimum 500, nilai persediaan 500.000 -
        // written as real numbers, not text, so Excel can sum them.
        $this->assertStringContainsString('<v>500</v>', $sheet);
        $this->assertStringContainsString('<v>1000</v>', $sheet);
        $this->assertStringContainsString('<v>500000</v>', $sheet);
    }

    public function test_workbook_carries_the_title_block_filter_row_and_frozen_header(): void
    {
        CompanyProfile::query()->create(['business_name' => 'YokPrinting Kelapa Dua']);
        $this->product('H-002', 'Produk Judul', 'Cup PET', ['track_stock' => false]);

        $sheet = $this->worksheetXml(
            $this->get(route('api.products.export.excel'))->assertOk()->getContent(),
        );

        $this->assertStringContainsString('LAPORAN DATA PRODUK', $sheet);
        $this->assertStringContainsString('YokPrinting Kelapa Dua', $sheet);
        $this->assertStringContainsString('PERIODE ', $sheet);
        // Title rows merged across all nine columns.
        $this->assertStringContainsString('<mergeCell ref="A1:I1"/>', $sheet);
        // Filter dropdowns on the header row.
        $this->assertStringContainsString('<autoFilter ref="A5:', $sheet);
        // Header row and title stay visible while scrolling.
        $this->assertStringContainsString('ySplit="5"', $sheet);
        $this->assertStringContainsString('state="frozen"', $sheet);
    }

    public function test_workbook_states_which_filters_produced_it(): void
    {
        $low = $this->product('LOW-01', 'Produk menipis', 'Cup PET', [
            'track_stock' => true, 'stock' => 10, 'minimum_stock' => 500,
        ]);
        $this->batch($low, qtyRemaining: 10, unitCost: 100);

        $sheet = $this->worksheetXml($this->get(route('api.products.export.excel', [
            'status' => 'low_stock',
            'category' => 'Cup PET',
            'q' => 'menipis',
        ]))->assertOk()->getContent());

        $this->assertStringContainsString('Status: Stok menipis', $sheet);
        $this->assertStringContainsString('Kategori: Cup PET', $sheet);
        $this->assertStringContainsString('Pencarian:', $sheet);
    }

    public function test_untracked_products_and_low_stock_status_are_reported_correctly(): void
    {
        $lowStock = $this->product('H-002', 'Cup Injection 14Oz', 'Cup Injection', [
            'track_stock' => true,
            'stock' => 100,
            'minimum_stock' => 500,
        ]);
        $this->batch($lowStock, qtyRemaining: 100, unitCost: 660);

        $this->product('JASA-01', 'Jasa desain', 'Jasa', [
            'track_stock' => false,
            'stock' => 0,
        ]);

        $this->product('H-003', 'Produk nonaktif', 'Cup Injection', [
            'track_stock' => true,
            'stock' => 900,
            'minimum_stock' => 500,
            'status' => Product::STATUS_INACTIVE,
        ]);

        $sheet = $this->worksheetXml(
            $this->get(route('api.products.export.excel'))->assertOk()->getContent(),
        );

        $this->assertStringContainsString('Stok menipis', $sheet);
        $this->assertStringContainsString('Tidak dilacak', $sheet);
        $this->assertStringContainsString('Nonaktif', $sheet);
    }

    public function test_exports_honour_the_status_category_and_search_filters(): void
    {
        $low = $this->product('LOW-01', 'Produk menipis', 'Cup PET', [
            'track_stock' => true, 'stock' => 10, 'minimum_stock' => 500,
        ]);
        $this->batch($low, qtyRemaining: 10, unitCost: 100);
        $this->product('OK-01', 'Produk aman', 'Cup PET', [
            'track_stock' => true, 'stock' => 900, 'minimum_stock' => 500,
        ]);
        $this->product('OTHER-01', 'Produk kategori lain', 'Cup PP', [
            'track_stock' => true, 'stock' => 900, 'minimum_stock' => 500,
        ]);

        $lowStockOnly = $this->worksheetXml(
            $this->get(route('api.products.export.excel', ['status' => 'low_stock']))->assertOk()->getContent(),
        );
        $this->assertStringContainsString('LOW-01', $lowStockOnly);
        $this->assertStringNotContainsString('OK-01', $lowStockOnly);

        $byCategory = $this->worksheetXml(
            $this->get(route('api.products.export.excel', ['category' => 'Cup PET']))->assertOk()->getContent(),
        );
        $this->assertStringContainsString('LOW-01', $byCategory);
        $this->assertStringContainsString('OK-01', $byCategory);
        $this->assertStringNotContainsString('OTHER-01', $byCategory);

        $bySearch = $this->worksheetXml(
            $this->get(route('api.products.export.excel', ['q' => 'kategori lain']))->assertOk()->getContent(),
        );
        $this->assertStringContainsString('OTHER-01', $bySearch);
        $this->assertStringNotContainsString('LOW-01', $bySearch);
    }

    public function test_export_query_is_validated(): void
    {
        $this->getJson(route('api.products.export.excel', ['status' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->getJson(route('api.products.export.pdf', ['status' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_pdf_export_returns_a_valid_pdf(): void
    {
        $tracked = $this->product('H-001', 'Cup Injection 12Oz Datar', 'Cup Injection', [
            'track_stock' => true,
            'stock' => 1000,
            'minimum_stock' => 500,
        ]);
        $this->batch($tracked, qtyRemaining: 1000, unitCost: 500);

        $response = $this->get(route('api.products.export.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString(
            'attachment; filename="data-produk-',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_there_is_no_csv_export_for_products(): void
    {
        $this->assertFalse(app('router')->has('api.products.export'));

        $this->get('/api/products/export')->assertNotFound();
    }

    public function test_product_page_shows_excel_and_pdf_buttons_but_no_csv(): void
    {
        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('excelExportUrl', false)
            ->assertSee('pdfExportUrl', false)
            ->assertSee('Excel')
            ->assertSee('PDF')
            ->assertDontSee('csvExportUrl', false);
    }

    public function test_both_exports_require_the_report_export_permission(): void
    {
        $routeNames = ['api.products.export.excel', 'api.products.export.pdf'];
        // Built once - role codes are unique, so re-creating them per route
        // would collide rather than test anything extra.
        $withoutPermission = $this->userWithPermissions(['product.view']);
        $withPermission = $this->userWithPermissions(['report.export']);

        auth()->logout();

        foreach ($routeNames as $routeName) {
            $this->getJson(route($routeName))->assertUnauthorized();
        }

        $this->actingAs($withoutPermission);

        foreach ($routeNames as $routeName) {
            $this->getJson(route($routeName))->assertForbidden();
        }

        $this->actingAs($withPermission);

        foreach ($routeNames as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    /**
     * Excel cell values are stored as XML - a product named `=cmd` is inert
     * inline text, never a formula, so no escaping prefix is needed or wanted.
     */
    public function test_special_characters_survive_the_workbook_intact(): void
    {
        $this->product('A&B-01', 'Produk <script> & "kutip"', 'Cup PP', ['track_stock' => false]);

        $sheet = $this->worksheetXml(
            $this->get(route('api.products.export.excel'))->assertOk()->getContent(),
        );

        $this->assertStringContainsString('A&amp;B-01', $sheet);
        $this->assertStringContainsString('&lt;script&gt;', $sheet);
    }

    private function worksheetXml(string $workbook): string
    {
        $path = tempnam(sys_get_temp_dir(), 'produk-xlsx-');
        file_put_contents($path, $workbook);

        try {
            $archive = new ZipArchive;
            $this->assertTrue($archive->open($path) === true, 'Workbook tidak dapat dibuka sebagai arsip zip.');
            $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
            $archive->close();
            $this->assertIsString($sheet);

            return $sheet;
        } finally {
            @unlink($path);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function product(string $sku, string $name, string $category, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'sku' => $sku,
            'name' => $name,
            'category' => $category,
            'price' => 1000,
            'status' => Product::STATUS_ACTIVE,
        ], $attributes));
    }

    private function batch(Product $product, float $qtyRemaining, float $unitCost): InventoryBatch
    {
        return InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => now()->toDateString(),
            'qty_received' => $qtyRemaining,
            'qty_remaining' => $qtyRemaining,
            'unit_cost' => $unitCost,
        ]);
    }

    /** @param list<string> $permissionCodes */
    private function userWithPermissions(array $permissionCodes): User
    {
        $role = Role::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            [$module, $action] = explode('.', $permissionCode, 2);
            $permission = Permission::factory()->create([
                'code' => $permissionCode,
                'module' => $module,
                'action' => $action,
            ]);
            $role->permissions()->attach($permission);
        }

        return User::factory()->create(['role' => $role->code]);
    }
}
