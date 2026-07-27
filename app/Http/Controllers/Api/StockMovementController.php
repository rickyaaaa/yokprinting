<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\RecordStockMovement;
use Illuminate\Http\JsonResponse;

class StockMovementController extends Controller
{
    public function store(
        StoreStockMovementRequest $request,
        RecordStockMovement $recordStockMovement,
    ): JsonResponse {
        $data = $request->validated();
        $product = Product::query()->findOrFail($data['product_id']);

        $movement = $data['type'] === StockMovement::TYPE_STOCK_OPNAME
            ? $recordStockMovement->stockOpname(
                product: $product,
                countedStock: $data['quantity'],
                referenceNumber: $data['reference_number'] ?? null,
                notes: $data['notes'] ?? null,
                userId: $request->user()?->getAuthIdentifier(),
            )
            : $recordStockMovement->record(
                product: $product,
                type: $data['type'],
                quantity: $data['quantity'],
                referenceNumber: $data['reference_number'] ?? null,
                notes: $data['notes'] ?? null,
                userId: $request->user()?->getAuthIdentifier(),
            );

        $movement->load('product');

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $movement->getKey(),
                'type' => $movement->type,
                'quantity' => (float) $movement->quantity,
                'stock_before' => (float) $movement->stock_before,
                'stock_after' => (float) $movement->stock_after,
                'reference_number' => $movement->reference_number,
                'product' => [
                    'id' => $movement->product->getKey(),
                    'sku' => $movement->product->sku,
                    'name' => $movement->product->name,
                    'stock' => (float) $movement->product->stock,
                    'minimum_stock' => (float) $movement->product->minimum_stock,
                ],
                'warning' => $this->warning($movement->product),
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function warning(Product $product): ?array
    {
        $stock = (float) ($product->stock ?? 0);
        $minimumStock = (float) ($product->minimum_stock ?? 0);

        if ($stock > $minimumStock && $stock >= 0) {
            return null;
        }

        return [
            'low_stock' => $stock <= $minimumStock,
            'negative_stock' => $stock < 0,
            'message' => $stock < 0
                ? 'Stok produk menjadi negatif.'
                : 'Stok produk sudah mencapai atau di bawah minimum.',
        ];
    }
}
