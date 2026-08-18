<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelPurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Purchasing\CancelPurchaseOrder;
use Illuminate\Http\JsonResponse;

class PurchaseOrderCancellationController extends Controller
{
    public function store(
        CancelPurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        CancelPurchaseOrder $cancelPurchaseOrder,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $updated = $cancelPurchaseOrder->handle($purchaseOrder, $actor, $request->validated('reason'));

        return response()->json([
            'message' => 'PO berhasil dibatalkan.',
            'data' => ['status' => $updated->status, 'status_label' => $updated->statusLabel()],
        ]);
    }
}
