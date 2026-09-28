<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class StockMutationReportPageController extends Controller
{
    public function __invoke(): View
    {
        $products = Product::query()
            ->select(['id', 'sku', 'name', 'unit'])
            ->selectable()
            ->orderBy('name')
            ->get();

        return view('reports.stock-mutations', [
            'products' => $products,
            'dateFrom' => now()->startOfMonth()->toDateString(),
            'dateTo' => now()->toDateString(),
        ]);
    }
}
