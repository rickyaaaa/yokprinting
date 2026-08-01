<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProductBulkStockApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_products_are_updated_by_one_bulk_request(): void
    {
        $this->actingAsUserWithProductUpdatePermission();
        $products = collect(range(1, 3))->map(
            fn (int $index): Product => $this->createProduct("BULK-{$index}"),
        );

        $this->patchJson(route('api.products.bulk-stock.update'), [
            'items' => [
                $this->bulkItem($products[0], 'stock', 1000),
                $this->bulkItem($products[1], 'minimum_stock', 0),
                $this->bulkItem($products[2], 'stock', 1500),
            ],
        ])
            ->assertOk()
            ->assertJsonPath('meta.updated_count', 3)
            ->assertJsonPath('data.0.id', $products[0]->id)
            ->assertJsonPath('data.0.stock', 1000)
            ->assertJsonPath('data.1.minimum_stock', 0)
            ->assertJsonPath('data.2.stock', 1500)
            ->assertJsonPath('data.0.updated_at', fn ($value): bool => is_string($value) && $value !== '');

        $this->assertDatabaseHas('products', ['id' => $products[0]->id, 'stock' => 1000]);
        $this->assertDatabaseHas('products', ['id' => $products[1]->id, 'minimum_stock' => 0]);
        $this->assertDatabaseHas('products', ['id' => $products[2]->id, 'stock' => 1500]);
    }

    public function test_failure_on_one_product_rolls_back_every_update(): void
    {
        $this->actingAsUserWithProductUpdatePermission();
        $first = $this->createProduct('BULK-OK');
        $second = $this->createProduct('BULK-FAIL');

        Product::updating(function (Product $product): void {
            if ($product->sku === 'BULK-FAIL') {
                throw new RuntimeException('Simulated bulk update failure.');
            }
        });

        $this->patchJson(route('api.products.bulk-stock.update'), [
            'items' => [
                $this->bulkItem($first, 'stock', 1500),
                $this->bulkItem($second, 'stock', 1500),
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.stock']);

        $this->assertDatabaseHas('products', [
            'id' => $first->id,
            'stock' => 500,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $second->id,
            'stock' => 500,
        ]);
    }

    public function test_invalid_product_list_does_not_update_valid_products(): void
    {
        $this->actingAsUserWithProductUpdatePermission();
        $product = $this->createProduct('BULK-VALID');

        $this->patchJson(route('api.products.bulk-stock.update'), [
            'items' => [
                $this->bulkItem($product, 'stock', 1500),
                [
                    'id' => 999999,
                    'field' => 'stock',
                    'value' => 1500,
                    'expected_value' => 500,
                    'expected_updated_at' => now()->toISOString(),
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.id']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 500,
        ]);
    }

    public function test_invalid_value_on_one_item_prevents_every_update(): void
    {
        $this->actingAsUserWithProductUpdatePermission();
        $first = $this->createProduct('BULK-VALID-VALUE');
        $second = $this->createProduct('BULK-INVALID-VALUE');

        $this->patchJson(route('api.products.bulk-stock.update'), [
            'items' => [
                $this->bulkItem($first, 'stock', 1500),
                $this->bulkItem($second, 'minimum_stock', -1),
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.value']);

        $this->assertDatabaseHas('products', ['id' => $first->id, 'stock' => 500]);
        $this->assertDatabaseHas('products', ['id' => $second->id, 'minimum_stock' => 500]);
    }

    public function test_duplicate_product_ids_are_rejected_before_update(): void
    {
        $this->actingAsUserWithProductUpdatePermission();
        $product = $this->createProduct('BULK-DUPLICATE');

        $this->patchJson(route('api.products.bulk-stock.update'), [
            'items' => [
                $this->bulkItem($product, 'stock', 1000),
                $this->bulkItem($product, 'minimum_stock', 1000),
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.id', 'items.1.id']);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 500, 'minimum_stock' => 500]);
    }

    public function test_stale_product_version_causes_conflict_without_partial_update(): void
    {
        $this->actingAsUserWithProductUpdatePermission();
        $first = $this->createProduct('BULK-FRESH');
        $second = $this->createProduct('BULK-STALE');
        $staleItem = $this->bulkItem($second, 'minimum_stock', 1000);
        $staleItem['expected_value'] = 0;

        $this->patchJson(route('api.products.bulk-stock.update'), [
            'items' => [
                $this->bulkItem($first, 'stock', 1000),
                $staleItem,
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.minimum_stock']);

        $this->assertDatabaseHas('products', ['id' => $first->id, 'stock' => 500]);
        $this->assertDatabaseHas('products', ['id' => $second->id, 'minimum_stock' => 500]);
    }

    public function test_user_without_product_update_permission_is_forbidden(): void
    {
        $this->actingAsUserWithoutProductUpdatePermission();
        $product = $this->createProduct('BULK-FORBIDDEN');

        $this->patchJson(route('api.products.bulk-stock.update'), [
            'items' => [$this->bulkItem($product, 'stock', 1500)],
        ])->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 500]);
    }

    private function createProduct(string $sku): Product
    {
        return Product::query()->create([
            'sku' => $sku,
            'name' => "Produk {$sku}",
            'stock' => 500,
            'minimum_stock' => 500,
            'track_stock' => true,
        ]);
    }

    /**
     * @return array{id: int, field: string, value: int, expected_value: float, expected_updated_at: string}
     */
    private function bulkItem(
        Product $product,
        string $field,
        int $value,
    ): array {
        return [
            'id' => (int) $product->getKey(),
            'field' => $field,
            'value' => $value,
            'expected_value' => $field === 'minimum_stock'
                ? $product->minimumStockValue()
                : (float) ($product->stock ?? 0),
            'expected_updated_at' => $product->updated_at->toISOString(),
        ];
    }

    private function actingAsUserWithProductUpdatePermission(): void
    {
        $role = Role::factory()->create(['code' => Role::CODE_OPERATIONS]);
        $permission = Permission::factory()->create([
            'code' => 'product.update',
            'module' => Permission::MODULE_PRODUCT,
            'action' => 'update',
        ]);
        $role->permissions()->attach($permission);

        $this->actingAs(User::factory()->create(['role' => $role->code]));
    }

    private function actingAsUserWithoutProductUpdatePermission(): void
    {
        $role = Role::factory()->create(['code' => Role::CODE_VIEWER]);

        $this->actingAs(User::factory()->create(['role' => $role->code]));
    }
}
