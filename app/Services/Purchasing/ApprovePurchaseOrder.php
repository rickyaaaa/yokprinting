<?php

namespace App\Services\Purchasing;

use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovePurchaseOrder
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * Approve a PO that's waiting for approval. Item prices were already
     * locked at creation time - approving never touches them.
     */
    public function handle(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->getKey());

            if ($locked->status !== PurchaseOrder::STATUS_WAITING_APPROVAL) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya PO yang sedang menunggu approval yang bisa disetujui.',
                ]);
            }

            $locked->forceFill([
                'status' => PurchaseOrder::STATUS_APPROVED,
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
            ])->save();

            $this->activityLogger->record(
                module: 'purchase_order',
                action: 'approved',
                event: 'Purchase order approved',
                description: "PO {$locked->po_number} disetujui.",
                subject: $locked,
                metadata: ['grand_total' => (string) $locked->grand_total],
                riskLevel: ActivityLog::RISK_HIGH,
                actor: $actor,
            );

            return $locked->refresh();
        });
    }
}
