<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Purchasing\SubmitPurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderSubmissionController extends Controller
{
    public function store(Request $request, PurchaseOrder $purchaseOrder, SubmitPurchaseOrder $submitPurchaseOrder): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $updated = $submitPurchaseOrder->handle($purchaseOrder, $actor);

        return response()->json([
            'message' => 'PO berhasil diajukan untuk approval.',
            'data' => ['status' => $updated->status, 'status_label' => $updated->statusLabel()],
        ]);
    }
}
