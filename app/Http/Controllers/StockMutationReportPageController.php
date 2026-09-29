<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StockMutationReportPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $role = $user->role === User::ROLE_OWNER
            ? null
            : $user->roleDefinition()->with('permissions')->first();
        $canExport = $user->isActive() && (
            $user->role === User::ROLE_OWNER
            || ($role && $role->status !== Role::STATUS_DISABLED
                && $role->permissions->contains('code', 'report.export'))
        );

        $products = Product::query()
            ->select(['id', 'sku', 'name', 'unit'])
            ->selectable()
            ->orderBy('name')
            ->get();

        return view('reports.stock-mutations', [
            'products' => $products,
            'dateFrom' => now()->startOfMonth()->toDateString(),
            'dateTo' => now()->toDateString(),
            'canExport' => $canExport,
        ]);
    }
}
