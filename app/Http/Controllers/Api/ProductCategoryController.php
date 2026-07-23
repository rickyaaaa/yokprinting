<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListProductCategoriesRequest;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ProductCategoryController extends Controller
{
    /**
     * Return active product categories for product forms and filters.
     */
    public function index(ListProductCategoriesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = trim($validated['search'] ?? '');
        $limit = (int) ($validated['limit'] ?? 100);

        $categories = ProductCategory::query()
            ->select(['id', 'name', 'slug', 'description', 'sort_order'])
            ->selectable()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (ProductCategory $category): array => [
                'id' => $category->getKey(),
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
            ])
            ->values();

        return response()->json([
            'data' => $categories,
            'meta' => [
                'count' => $categories->count(),
                'limit' => $limit,
            ],
        ]);
    }
}
