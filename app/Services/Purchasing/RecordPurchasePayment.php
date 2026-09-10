<?php

namespace App\Services\Purchasing;

use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
use App\Services\CashBank\CashBankService;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPurchasePayment
{
    public function __construct(
        private readonly GeneratePurchasePaymentNumber $generatePaymentNumber,
        private readonly SyncPurchaseOrderPaymentStatus $syncPaymentStatus,
        private readonly CashBankService $cashBank,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * Record a verified supplier payment and its cash outflow atomically.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(PurchaseOrder $purchaseOrder, array $data, ?int $recordedBy = null): PurchasePayment
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $recordedBy): PurchasePayment {
            /** @var PurchaseOrder $lockedOrder */
            $lockedOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->getKey());

            if ($lockedOrder->status === PurchaseOrder::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'PO yang sudah dibatalkan tidak bisa dibayar.',
                ]);
            }

            if ($lockedOrder->status === PurchaseOrder::STATUS_CLOSED) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'PO yang sudah ditutup tidak bisa dibayar.',
                ]);
            }

            $paidBefore = (float) $lockedOrder->purchasePayments()
                ->verified()
                ->lockForUpdate()
                ->sum('amount');
            $remaining = round(max(0, (float) $lockedOrder->grand_total - $paidBefore), 2, PHP_ROUND_HALF_UP);
            $amount = round((float) $data['amount'], 2, PHP_ROUND_HALF_UP);

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'PO ini sudah lunas.',
                ]);
            }

            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran melebihi sisa hutang PO.',
                ]);
            }

            $payment = $lockedOrder->purchasePayments()->create([
                'payment_number' => $this->generatePaymentNumber->generate(Carbon::parse($data['payment_date'])),
                'payment_date' => $data['payment_date'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'amount' => $amount,
                'status' => PurchasePayment::STATUS_VERIFIED,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $recordedBy,
                'verified_at' => now(),
                'verified_by' => $recordedBy,
            ]);

            $this->syncPaymentStatus->handle($lockedOrder);
            $this->cashBank->recordPurchasePayment($payment);

            $this->activityLogger->record(
                module: 'purchase_payment',
                action: 'verified',
                event: 'Purchase payment verified',
                description: "Pembayaran {$payment->payment_number} untuk PO {$lockedOrder->po_number} diverifikasi.",
                subject: $payment,
                metadata: [
                    'purchase_order_id' => $lockedOrder->getKey(),
                    'amount' => (string) $payment->amount,
                    'cash_bank_source' => 'purchase_payment',
                ],
                riskLevel: ActivityLog::RISK_HIGH,
            );

            return $payment->load('purchaseOrder', 'cashBankTransaction');
        });
    }
}
