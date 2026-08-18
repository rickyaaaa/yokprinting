<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Services\Purchasing\CancelGoodsReceipt;
use Illuminate\Http\JsonResponse;

class GoodsReceiptCancellationController extends Controller
{
    public function store(
        CancelGoodsReceiptRequest $request,
        GoodsReceipt $goodsReceipt,
        CancelGoodsReceipt $cancelGoodsReceipt,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $updated = $cancelGoodsReceipt->handle($goodsReceipt, $actor, $request->validated('reason'));

        return response()->json([
            'message' => 'Penerimaan barang berhasil dibatalkan.',
            'data' => ['status' => $updated->status, 'status_label' => $updated->statusLabel()],
        ]);
    }
}
