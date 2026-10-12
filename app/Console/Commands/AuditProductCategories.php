<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Console\Command;

class AuditProductCategories extends Command
{
    protected $signature = 'app:audit-product-categories
        {--json : Print the read-only audit as JSON}';

    protected $description = 'Audit canonical and legacy product categories without changing data.';

    public function handle(): int
    {
        $missingCategory = Product::query()->whereNull('category_id')->where(function ($query): void {
            $query->whereNull('category')->orWhere('category', '');
        })->count();
        $relationWithoutLegacy = Product::query()
            ->whereNotNull('category_id')
            ->where(function ($query): void {
                $query->whereNull('category')->orWhere('category', '');
            })->count();
        $legacyWithoutRelation = Product::query()
            ->whereNull('category_id')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->count();

        $report = [
            'products_total' => Product::query()->count(),
            'missing_category' => $missingCategory,
            'relation_without_legacy' => $relationWithoutLegacy,
            'legacy_without_relation' => $legacyWithoutRelation,
            'services_by_track_stock_false' => Product::query()->where('track_stock', false)->count(),
            'categories' => ProductCategory::query()
                ->orderBy('status')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'status', 'sort_order'])
                ->map(fn (ProductCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'status' => $category->status,
                    'sort_order' => $category->sort_order,
                    'product_count' => $category->products()->count(),
                ])->values()->all(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Audit kategori produk (read-only): tidak ada data yang diubah.');
        $this->table([
            'Metrik', 'Jumlah',
        ], [
            ['Total produk', $report['products_total']],
            ['Tanpa kategori', $report['missing_category']],
            ['category_id ada, legacy kosong', $report['relation_without_legacy']],
            ['Legacy ada, category_id kosong', $report['legacy_without_relation']],
            ['Jasa / track_stock=false', $report['services_by_track_stock_false']],
        ]);
        $this->table(['ID', 'Kategori', 'Status', 'Jumlah produk'], collect($report['categories'])
            ->map(fn (array $category): array => [$category['id'], $category['name'], $category['status'], $category['product_count']])
            ->all());

        return self::SUCCESS;
    }
}
