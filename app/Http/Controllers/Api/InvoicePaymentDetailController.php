<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class InvoicePaymentDetailController extends Controller
{
    /**
     * Return invoice payment detail with remaining balance information.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['customer', 'items', 'payments' => fn ($query) => $query->latest('payment_date')]);

        $paidAmount = (float) $invoice->payments
            ->where('status', Payment::STATUS_VERIFIED)
            ->sum('amount');
        $totalAmount = (float) $invoice->total_amount;
        $remainingAmount = max(0, $totalAmount - $paidAmount);
        $progress = $totalAmount > 0
            ? min(100, round(($paidAmount / $totalAmount) * 100, 2))
            : 0;
        $isOverdue = $invoice->due_date->isPast()
            && $invoice->payment_status !== Invoice::PAYMENT_PAID;

        return response()->json([
            'status' => 'success',
            'data' => [
                'invoice' => [
                    'id' => $invoice->getKey(),
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'payment_status' => $isOverdue ? Invoice::PAYMENT_OVERDUE : $invoice->payment_status,
                    'payment_status_label' => $this->paymentStatusLabel(
                        $isOverdue ? Invoice::PAYMENT_OVERDUE : $invoice->payment_status,
                    ),
                    'production_status' => $invoice->production_status,
                    'production_status_label' => $invoice->productionStatusLabel(),
                    'issue_date' => $invoice->issue_date->toDateString(),
                    'due_date' => $invoice->due_date->toDateString(),
                    'is_overdue' => $isOverdue,
                    'currency' => $invoice->currency,
                    'subtotal' => (float) $invoice->subtotal,
                    'discount_amount' => (float) $invoice->discount_amount,
                    'tax_amount' => (float) $invoice->tax_amount,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $remainingAmount,
                    'required_dp_amount' => $invoice->requiredDpAmount(),
                    'dp_required_percent' => (float) $invoice->dp_required_percent,
                    'payment_progress' => $progress,
                    'total_amount_formatted' => $this->formatRupiah($totalAmount),
                    'paid_amount_formatted' => $this->formatRupiah($paidAmount),
                    'remaining_amount_formatted' => $this->formatRupiah($remainingAmount),
                    'required_dp_amount_formatted' => $this->formatRupiah($invoice->requiredDpAmount()),
                    'design_notes' => $invoice->design_notes,
                    'mockup_url' => $invoice->mockup_url,
                    'notes' => $invoice->notes,
                    'terms' => $invoice->terms,
                ],
                'customer' => [
                    'id' => $invoice->customer?->getKey(),
                    'name' => $invoice->customer?->name,
                    'email' => $invoice->customer?->email,
                    'phone' => $invoice->customer?->phone,
                    'address' => $invoice->customer?->address,
                ],
                'items' => $invoice->items->map(fn ($item): array => [
                    'id' => $item->getKey(),
                    'product_name' => $item->product_name,
                    'sku' => $item->sku,
                    'cup_size' => $item->cup_size,
                    'cup_model' => $item->cup_model,
                    'grammage' => $item->grammage,
                    'screen_printing_color' => $item->screen_printing_color,
                    'jenis_cetak' => $item->jenis_cetak,
                    'moq_quantity' => $item->moq_quantity,
                    'order_increment' => $item->order_increment,
                    'packaging_unit' => $item->packaging_unit,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                    'total_amount' => (float) $item->total_amount,
                ])->values(),
                'payments' => $invoice->payments->map(fn (Payment $payment): array => [
                    'id' => $payment->getKey(),
                    'payment_number' => $payment->payment_number,
                    'payment_date' => $payment->payment_date->toDateString(),
                    'method' => $payment->method,
                    'method_label' => $payment->methodLabel(),
                    'reference' => $payment->reference,
                    'amount' => (float) $payment->amount,
                    'amount_formatted' => $this->formatRupiah((float) $payment->amount),
                    'status' => $payment->status,
                    'status_label' => $payment->statusLabel(),
                ])->values(),
            ],
        ]);
    }

    private function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            Invoice::PAYMENT_PAID => 'Lunas',
            Invoice::PAYMENT_PARTIAL => 'Parsial',
            Invoice::PAYMENT_OVERDUE => 'Overdue',
            default => 'Menunggu',
        };
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
