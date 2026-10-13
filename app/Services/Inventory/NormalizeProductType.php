<?php

namespace App\Services\Inventory;

use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use Illuminate\Validation\ValidationException;

class NormalizeProductType
{
    /** @param array<string, mixed> $payload */
    public function handle(array $payload, ?Product $product = null): array
    {
        $type = $payload['product_type'] ?? $product?->product_type;
        $isService = $type === Product::TYPE_SERVICE;
        $tracksStock = $isService ? false : (bool) ($payload['track_stock'] ?? $product?->track_stock ?? false);

        // Old invoices without a snapshot must keep their old stock behavior.
        $behaviorChanged = $product && ($isService !== $product->isService() || $tracksStock !== $product->tracksInventory());
        if ($behaviorChanged && (
            (float) $product->stock !== 0.0
            || $product->inventoryBatches()->exists()
            || $product->stockMovements()->exists()
            || InvoiceItem::withTrashed()->where('product_id', $product->id)->exists()
            || PurchaseOrderItem::query()->where('product_id', $product->id)->exists()
        )) {
            throw ValidationException::withMessages([
                'product_type' => 'Jenis/pelacakan stok tidak dapat diubah karena produk memiliki stok atau histori transaksi. Buat item katalog baru untuk jenis yang berbeda.',
            ]);
        }

        if ($isService) {
            $payload['product_type'] = Product::TYPE_SERVICE;
            $payload['track_stock'] = false;
            $payload['stock'] = null;
            $payload['minimum_stock'] = null;
            unset($payload['manual_unit_cost']);
            $payload['unit'] = $payload['unit'] ?? ($product?->isService() ? $product->unit : Product::UNIT_PCS);
            foreach (['minimum_order_qty', 'package_conversion', 'moq_quantity', 'order_increment'] as $field) {
                $payload[$field] = 1;
            }
        }

        return $payload;
    }
}
