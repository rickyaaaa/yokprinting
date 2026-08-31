<?php

namespace Tests\Feature;

use App\Models\InventoryBatch;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * Client request: "tambahin tarikan/eksport data produk atau stok".
 * A point-in-time catalog snapshot, distinct from the period-based
 * stock-mutation export which only covers products that actually moved.
 */
class ProductCatalogExportApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_export_returns_the_agreed_columns_for_every_product(): void
    {
        $tracked = $this->product('H-001', 'Cup Injection 12Oz Datar', 'Cup Injection', [
            'track_stock' => true,
            'stock' => 1000,
            'minimum_stock' => 500,
        ]);
        $this->batch($tracked, qtyRemaining: 1000, unitCost: 500);

        $response = $this->get(route('api.products.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString(
            'attachment; filename="data-produk-',
            (string) $response->headers->get('content-disposition'),
        );

        $content = $response->getContent();

        $this->assertStringStartsWith(
            "\u{FEFF}SKU,\"Nama Produk\",Kategori,Unit,\"HPP FIFO\",Stok,\"Minimum Stok\",Status,\"Nilai Persediaan\"",
            $content,
        );
        // HPP FIFO 500, stok 1000, minimum 500, nilai persediaan 500 * 1000.
        $this->assertStringContainsString(
            'H-001,"Cup Injection 12Oz Datar","Cup Injection",PCS,500,1000,500,Aktif,500000',
            $content,
        );
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

        $content = $this->get(route('api.products.export'))->assertOk()->getContent();

        $this->assertStringContainsString('H-002,"Cup Injection 14Oz","Cup Injection",PCS,660,100,500,"Stok menipis",66000', $content);
        $this->assertStringContainsString('JASA-01,"Jasa desain",Jasa,PCS,0,"Tidak dilacak","Tidak dilacak",Aktif,0', $content);
        $this->assertStringContainsString('H-003,"Produk nonaktif","Cup Injection",PCS,0,900,500,Nonaktif,0', $content);
    }

    public function test_export_honours_the_status_category_and_search_filters(): void
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

        $lowStockOnly = $this->get(route('api.products.export', ['status' => 'low_stock']))->assertOk()->getContent();
        $this->assertStringContainsString('LOW-01', $lowStockOnly);
        $this->assertStringNotContainsString('OK-01', $lowStockOnly);

        $byCategory = $this->get(route('api.products.export', ['category' => 'Cup PET']))->assertOk()->getContent();
        $this->assertStringContainsString('LOW-01', $byCategory);
        $this->assertStringContainsString('OK-01', $byCategory);
        $this->assertStringNotContainsString('OTHER-01', $byCategory);

        $bySearch = $this->get(route('api.products.export', ['q' => 'kategori lain']))->assertOk()->getContent();
        $this->assertStringContainsString('OTHER-01', $bySearch);
        $this->assertStringNotContainsString('LOW-01', $bySearch);
    }

    public function test_export_query_is_validated(): void
    {
        $this->getJson(route('api.products.export', ['status' => 'unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_export_escapes_spreadsheet_formula_cells(): void
    {
        $this->product('=CMD-01', '+SUM(1,1)', '@evil', ['track_stock' => false]);

        $content = $this->get(route('api.products.export'))->assertOk()->getContent();

        $this->assertStringContainsString("'=CMD-01", $content);
        $this->assertStringContainsString("'+SUM(1,1)", $content);
        $this->assertStringContainsString("'@evil", $content);
    }

    public function test_export_requires_the_report_export_permission(): void
    {
        auth()->logout();
        $this->getJson(route('api.products.export'))->assertUnauthorized();

        $this->actingAs($this->userWithPermissions(['product.view']));
        $this->getJson(route('api.products.export'))->assertForbidden();

        $this->actingAs($this->userWithPermissions(['report.export']));
        $this->get(route('api.products.export'))->assertOk();
    }

    public function test_product_page_shows_the_export_button_for_permitted_users(): void
    {
        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('exportUrl', false)
            ->assertSee('Export');
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
