<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Purchasing\ApprovePurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderApprovalController extends Controller
{
    public function store(Request $request, PurchaseOrder $purchaseOrder, ApprovePurchaseOrder $approvePurchaseOrder): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $updated = $approvePurchaseOrder->handle($purchaseOrder, $actor);

        return response()->json([
            'message' => 'PO berhasil disetujui.',
            'data' => [
                'status' => $updated->status,
                'status_label' => $updated->statusLabel(),
                'approved_by' => $updated->approver?->name,
                'approved_at' => $updated->approved_at?->toISOString(),
            ],
        ]);
    }
}
