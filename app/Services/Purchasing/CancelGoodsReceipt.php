<?php

namespace App\Services\Purchasing;

use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelGoodsReceipt
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * Cancel a goods receipt. Only allowed while still draft - a posted
     * receipt has already changed stock and cost, and reversing that
     * accurately (when later receipts may have moved the average cost
     * again) isn't implemented in this phase.
     */
    public function handle(GoodsReceipt $goodsReceipt, User $actor, ?string $reason): GoodsReceipt
    {
        return DB::transaction(function () use ($goodsReceipt, $actor, $reason): GoodsReceipt {
            /** @var GoodsReceipt $locked */
            $locked = GoodsReceipt::query()->lockForUpdate()->findOrFail($goodsReceipt->getKey());

            if ($locked->status !== GoodsReceipt::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => $locked->status === GoodsReceipt::STATUS_POSTED
                        ? 'Penerimaan barang yang sudah diposting tidak bisa dibatalkan.'
                        : 'Penerimaan barang ini sudah dibatalkan sebelumnya.',
                ]);
            }

            $locked->forceFill([
                'status' => GoodsReceipt::STATUS_CANCELLED,
                'cancelled_by' => $actor->getKey(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            $this->activityLogger->record(
                module: 'goods_receipt',
                action: 'cancelled',
                event: 'Goods receipt cancelled',
                description: "Penerimaan barang {$locked->receipt_number} dibatalkan.",
                subject: $locked,
                metadata: array_filter(['reason' => $reason]),
                riskLevel: ActivityLog::RISK_MEDIUM,
                actor: $actor,
            );

            return $locked->refresh();
        });
    }
}
