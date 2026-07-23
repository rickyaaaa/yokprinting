<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListCustomerOptionsRequest;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CustomerOptionController extends Controller
{
    /**
     * Return active customers for transactional dropdowns.
     */
    public function index(ListCustomerOptionsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $search = trim($validated['search'] ?? '');
        $limit = (int) ($validated['limit'] ?? 25);

        $customers = Customer::query()
            ->select(['id', 'name', 'email', 'phone', 'address'])
            ->selectable()
            ->when(
                filled($validated['ids'] ?? null),
                fn (Builder $query): Builder => $query->whereIn('id', $validated['ids']),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->getKey(),
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'initials' => $customer->initials(),
            ])
            ->values();

        return response()->json([
            'data' => $customers,
            'meta' => [
                'count' => $customers->count(),
                'limit' => $limit,
            ],
        ]);
    }
}
