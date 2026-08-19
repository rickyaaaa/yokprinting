<?php

namespace App\Services\Purchasing;

use App\Models\SupplierPriceList;
use Illuminate\Support\Carbon;

class ResolveActiveSupplierPrice
{
    /**
     * Find the supplier price currently in effect for a supplier + product
     * pair: valid_from <= today AND (valid_until IS NULL OR valid_until >= today).
     * When several quotes are active at once, the most recently started one
     * wins, tie-broken by the newest id - deterministic and never mutates
     * or deletes older history.
     */
    public function active(int $supplierId, int $productId, ?Carbon $onDate = null): ?SupplierPriceList
    {
        $today = $onDate ?? Carbon::today();

        return SupplierPriceList::query()
            ->where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->activeOn($today)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Fall back to the supplier's most recent quote for this product even
     * if it has already expired or hasn't started yet - used so the UI can
     * still show "harga terakhir supplier" with an expired/upcoming badge
     * when there is no currently active quote.
     */
    public function latest(int $supplierId, int $productId): ?SupplierPriceList
    {
        return SupplierPriceList::query()
            ->where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Resolve what to suggest for a supplier + product pair: the active
     * quote if there is one, otherwise the latest known quote (flagged as
     * expired/upcoming by the caller via SupplierPriceList::status()).
     */
    public function suggest(int $supplierId, int $productId, ?Carbon $onDate = null): ?SupplierPriceList
    {
        return $this->active($supplierId, $productId, $onDate) ?? $this->latest($supplierId, $productId);
    }
}
