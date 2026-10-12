<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditProductCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_audit_reports_inconsistencies_without_writing(): void
    {
        $category = ProductCategory::query()->create(['name' => 'Jasa']);
        Product::query()->create(['sku' => 'MISSING-001', 'name' => 'Tanpa kategori', 'track_stock' => true]);
        Product::query()->create([
            'sku' => 'REL-001', 'name' => 'Relasi jasa', 'category_id' => $category->id, 'category' => null, 'track_stock' => true,
        ]);
        Product::query()->create(['sku' => 'LEGACY-001', 'name' => 'Legacy jasa', 'category' => 'Jasa', 'track_stock' => true]);
        Product::query()->create(['sku' => 'SERVICE-001', 'name' => 'Jasa tanpa stok', 'track_stock' => false]);

        $this->artisan('app:audit-product-categories --json')
            ->assertSuccessful();

        $this->assertDatabaseCount('products', 4);
        $this->assertSame(2, Product::query()->whereNull('category_id')->where(fn ($query) => $query->whereNull('category')->orWhere('category', ''))->count());
        $this->assertSame(1, Product::query()->whereNotNull('category_id')->where(fn ($query) => $query->whereNull('category')->orWhere('category', ''))->count());
        $this->assertSame(1, Product::query()->whereNull('category_id')->whereNotNull('category')->where('category', '!=', '')->count());
        $this->assertSame(1, Product::query()->where('track_stock', false)->count());
        $this->assertDatabaseHas('products', ['sku' => 'REL-001', 'category_id' => $category->id, 'category' => null]);
    }
}
