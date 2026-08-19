<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActiveSupplierPriceRequest;
use App\Models\SupplierPriceList;
use App\Services\Purchasing\ResolveActiveSupplierPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SupplierPriceActiveController extends Controller
{
    public function __construct(private readonly ResolveActiveSupplierPrice $resolveActiveSupplierPrice) {}

    /**
     * Suggest the price to use for a supplier + product pair on a PO: the
     * currently active quote, or - if none is active - the supplier's most
     * recent quote flagged as expired/upcoming so the UI can still show it
     * with a warning (requirement 6/11).
     */
    public function show(ActiveSupplierPriceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $today = Carbon::today();

        $priceList = $this->resolveActiveSupplierPrice->suggest(
            (int) $data['supplier_id'],
            (int) $data['product_id'],
            $today,
        );

        if (! $priceList instanceof SupplierPriceList) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id' => $priceList->getKey(),
                'price' => (float) $priceList->price,
                'valid_from' => $priceList->valid_from?->toDateString(),
                'valid_until' => $priceList->valid_until?->toDateString(),
                'status' => $priceList->status($today),
                'status_label' => $priceList->statusLabel($today),
                'notes' => $priceList->notes,
            ],
        ]);
    }
}
