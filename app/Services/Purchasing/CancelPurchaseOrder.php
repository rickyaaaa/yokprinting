<?php

namespace App\Services\Purchasing;

use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPurchaseOrder
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(PurchaseOrder $purchaseOrder, User $actor, ?string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $reason): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->getKey());

            if (! $locked->canBeCancelled()) {
                throw ValidationException::withMessages([
                    'status' => $locked->status === PurchaseOrder::STATUS_CANCELLED
                        ? 'PO ini sudah dibatalkan sebelumnya.'
                        : 'PO yang sudah ditutup tidak bisa dibatalkan.',
                ]);
            }

            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => PurchaseOrder::STATUS_CANCELLED,
                'cancelled_by' => $actor->getKey(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            $this->activityLogger->record(
                module: 'purchase_order',
                action: 'cancelled',
                event: 'Purchase order cancelled',
                description: "PO {$locked->po_number} dibatalkan.",
                subject: $locked,
                metadata: array_filter(['before' => $previousStatus, 'reason' => $reason]),
                riskLevel: ActivityLog::RISK_HIGH,
                actor: $actor,
            );

            return $locked->refresh();
        });
    }
}
