<?php

namespace App\Services\Purchasing;

use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Models\User;
use App\Services\CashBank\CashBankService;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPurchasePayment
{
    public function __construct(
        private readonly SyncPurchaseOrderPaymentStatus $syncPaymentStatus,
        private readonly CashBankService $cashBank,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(PurchasePayment $payment, User $actor, ?string $reason = null): PurchasePayment
    {
        return DB::transaction(function () use ($payment, $actor, $reason): PurchasePayment {
            $purchaseOrderId = PurchasePayment::query()->whereKey($payment->getKey())->value('purchase_order_id');

            if ($purchaseOrderId === null) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran PO tidak ditemukan.']);
            }

            /** @var PurchaseOrder $lockedOrder */
            $lockedOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrderId);
            /** @var PurchasePayment $lockedPayment */
            $lockedPayment = PurchasePayment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPayment->setRelation('purchaseOrder', $lockedOrder);

            if ($lockedPayment->status === PurchasePayment::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran PO ini sudah dibatalkan.',
                ]);
            }

            if ($lockedPayment->status !== PurchasePayment::STATUS_VERIFIED) {
                throw ValidationException::withMessages([
                    'payment' => 'Hanya pembayaran terverifikasi yang bisa dibatalkan.',
                ]);
            }

            $lockedPayment->forceFill([
                'status' => PurchasePayment::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->getKey(),
                'cancellation_reason' => $reason,
            ])->save();

            $this->cashBank->cancelPurchasePaymentTransaction($lockedPayment, $actor->getKey());
            $this->syncPaymentStatus->handle($lockedOrder);

            $this->activityLogger->record(
                module: 'purchase_payment',
                action: 'cancelled',
                event: 'Purchase payment cancelled',
                description: "Pembayaran {$lockedPayment->payment_number} dibatalkan.",
                subject: $lockedPayment,
                metadata: array_filter([
                    'purchase_order_id' => $lockedPayment->purchase_order_id,
                    'reason' => $reason,
                ]),
                riskLevel: ActivityLog::RISK_HIGH,
                actor: $actor,
            );

            return $lockedPayment->refresh()->load('purchaseOrder', 'cashBankTransaction');
        });
    }
}
