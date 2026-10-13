<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class AuditProductTypes extends Command
{
    protected $signature = 'app:audit-product-types {--json : Output a read-only JSON report}';

    protected $description = 'List unclassified legacy products for reviewed goods/service mapping; never writes data.';

    public function handle(): int
    {
        $unclassified = Product::query()->whereNull('product_type')->orderBy('sku')->get();
        $nonPcsServices = Product::query()->where('product_type', Product::TYPE_SERVICE)
            ->where(fn ($query) => $query->whereNull('unit')->orWhereRaw('LOWER(TRIM(unit)) != ?', ['pcs']))
            ->orderBy('sku')->get(['id', 'sku', 'name', 'unit']);
        $report = [
            'read_only' => true,
            'unclassified_count' => $unclassified->count(),
            'unclassified_non_stock_count' => $unclassified->where('track_stock', false)->count(),
            'service_non_pcs_count' => $nonPcsServices->count(),
            'services_needing_unit_review' => $nonPcsServices->toArray(),
            'products_needing_review' => $unclassified->map(fn (Product $product): array => [
                'id' => $product->id, 'sku' => $product->sku, 'name' => $product->name,
                'category' => $product->categoryLabel(), 'track_stock' => $product->track_stock,
                'stock' => $product->stock,
            ])->all(),
        ];
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
