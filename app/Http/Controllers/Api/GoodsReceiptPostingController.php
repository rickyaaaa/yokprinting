<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Services\Purchasing\PostGoodsReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiptPostingController extends Controller
{
    public function store(Request $request, GoodsReceipt $goodsReceipt, PostGoodsReceipt $postGoodsReceipt): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $updated = $postGoodsReceipt->handle($goodsReceipt, $actor);

        return response()->json([
            'message' => 'Penerimaan barang berhasil diposting. Stok dan biaya rata-rata sudah diperbarui.',
            'data' => [
                'status' => $updated->status,
                'status_label' => $updated->statusLabel(),
                'posted_by' => $updated->poster?->name,
                'posted_at' => $updated->posted_at?->toISOString(),
            ],
        ]);
    }
}
