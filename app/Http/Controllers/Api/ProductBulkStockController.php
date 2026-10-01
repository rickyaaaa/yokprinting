<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateProductStockRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\RecordStockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductBulkStockController extends Controller
{
    /**
     * Atomically update one stock field for the selected products.
     */
    public function __invoke(BulkUpdateProductStockRequest $request, RecordStockMovement $recordStockMovement): JsonResponse
    {
        $validated = $request->validated();
        $items = collect($validated['items'])
            ->values()
            ->map(fn (array $item): array => [
                'id' => (int) $item['id'],
                'field' => $item['field'],
                'value' => (float) $item['value'],
                'expected_value' => (float) $item['expected_value'],
                'expected_updated_at' => CarbonImmutable::parse($item['expected_updated_at']),
            ]);

        $products = DB::transaction(function () use ($items, $recordStockMovement, $request) {
            $products = Product::query()
                ->whereKey($items->pluck('id'))
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Product $product): int => (int) $product->getKey());

            $errors = [];

            foreach ($items as $index => $item) {
                $product = $products->get($item['id']);

                if (! $product instanceof Product) {
                    $errors["items.{$index}.id"] = ["Produk dengan ID {$item['id']} tidak ditemukan."];

                    continue;
                }

                $currentValue = $item['field'] === 'minimum_stock'
                    ? $product->minimumStockValue()
                    : (float) ($product->stock ?? 0);

                if (
                    ! $product->updated_at?->equalTo($item['expected_updated_at'])
                    || $currentValue !== $item['expected_value']
                ) {
                    $errors["items.{$index}.{$item['field']}"] = [
                        "Produk {$product->sku} telah berubah. Muat ulang data sebelum memperbarui {$item['field']}.",
                    ];
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($items as $index => $item) {
                try {
                    $product = $products->get($item['id']);

                    if ($item['field'] === 'stock' && $product->track_stock) {
                        $delta = round($item['value'] - (float) ($product->stock ?? 0), 4);

                        if ($delta !== 0.0) {
                            $recordStockMovement->record(
                                product: $product,
                                type: StockMovement::TYPE_ADJUSTMENT,
                                quantity: $delta,
                                referenceNumber: 'BULK-STOCK-'.Str::upper(Str::random(10)),
                                notes: 'Koreksi stok melalui Bulk edit stok.',
                                userId: $request->user()?->getAuthIdentifier(),
                            );
                        }
                    } else {
                        // Minimum stock is a catalogue threshold, not an
                        // inventory movement. Non-stock-tracked products also
                        // have no physical ledger to reconcile.
                        $product->update([$item['field'] => $item['value']]);
                    }
                } catch (Throwable) {
                    throw ValidationException::withMessages([
                        "items.{$index}.{$item['field']}" => [
                            "Produk ID {$item['id']} gagal diperbarui pada field {$item['field']}.",
                        ],
                    ]);
                }
            }

            return $items
                ->map(fn (array $item): Product => $products->get($item['id'])->refresh())
                ->values();
        });

        return response()->json([
            'message' => 'Stok produk berhasil diperbarui.',
            'data' => $products->map(fn (Product $product): array => [
                'id' => $product->getKey(),
                'stock' => $product->stock === null ? null : (float) $product->stock,
                'minimum_stock' => $product->minimumStockValue(),
                'updated_at' => $product->updated_at?->toISOString(),
            ])->all(),
            'meta' => [
                'updated_count' => $products->count(),
            ],
        ]);
    }
}
