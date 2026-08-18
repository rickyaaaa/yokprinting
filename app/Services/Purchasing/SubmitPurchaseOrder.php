<?php

namespace App\Services\Purchasing;

use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitPurchaseOrder
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * Move a draft PO into the approval queue.
     */
    public function handle(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor): PurchaseOrder {
            /** @var PurchaseOrder $locked */
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->getKey());

            if ($locked->status !== PurchaseOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya PO berstatus draft yang bisa diajukan untuk approval.',
                ]);
            }

            if (! $locked->items()->exists()) {
                throw ValidationException::withMessages([
                    'items' => 'PO tidak punya item, tambahkan minimal satu barang sebelum diajukan.',
                ]);
            }

            $locked->forceFill(['status' => PurchaseOrder::STATUS_WAITING_APPROVAL])->save();

            $this->activityLogger->record(
                module: 'purchase_order',
                action: 'submitted',
                event: 'Purchase order submitted for approval',
                description: "PO {$locked->po_number} diajukan untuk approval.",
                subject: $locked,
                riskLevel: ActivityLog::RISK_MEDIUM,
                actor: $actor,
            );

            return $locked->refresh();
        });
    }
}
