<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Contracts\View\View;

class ReceivablePageController extends Controller
{
    public function __invoke(): View
    {
        $invoices = Invoice::query()
            ->with('customer')
            ->withSum(['payments as verified_paid_amount' => fn ($query) => $query->verified()], 'amount')
            ->receivable()
            // Nearest due date first (most actionable for collections) is the
            // intentional default here, not a "recency" list - deliberately
            // NOT flipped to newest-first. Deterministic secondary sort by id
            // only, so same-day invoices don't shuffle between requests.
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $receivables = $invoices->map(function (Invoice $invoice): array {
            $paid = (float) ($invoice->verified_paid_amount ?? 0);
            $remaining = max(0, (float) $invoice->total_amount - $paid);
            $status = $invoice->due_date->isPast()
                ? 'Overdue'
                : ($invoice->payment_status === Invoice::PAYMENT_PARTIAL ? 'Parsial' : 'Menunggu');

            return [
                'invoice' => $invoice->invoice_number,
                'customer' => $invoice->customer?->name ?? 'Pelanggan tidak tersedia',
                'issued' => $invoice->issue_date->format('d M Y'),
                'due' => $invoice->due_date->format('d M Y'),
                'dueSort' => (int) $invoice->due_date->format('Ymd'),
                'total' => $this->rupiah((float) $invoice->total_amount),
                'paid' => $this->rupiah($paid),
                'outstanding' => $this->rupiah($remaining),
                'outstandingValue' => $remaining,
                'status' => $status,
            ];
        })->values();

        $overdue = $receivables->where('status', 'Overdue');
        $dueSoon = $receivables->filter(fn (array $row): bool => $row['status'] !== 'Overdue' && $row['dueSort'] <= (int) today()->addDays(7)->format('Ymd')
        );
        $notDue = $receivables->filter(fn (array $row): bool => $row['status'] !== 'Overdue' && $row['dueSort'] > (int) today()->addDays(7)->format('Ymd')
        );

        return view('payments.receivables', [
            'receivables' => $receivables,
            'summaryCards' => [
                ['label' => 'Total piutang', 'value' => $this->rupiah((float) $receivables->sum('outstandingValue')), 'caption' => $receivables->count().' invoice aktif', 'tone' => 'brand'],
                ['label' => 'Belum jatuh tempo', 'value' => $this->rupiah((float) $notDue->sum('outstandingValue')), 'caption' => $notDue->count().' invoice', 'tone' => 'success'],
                ['label' => 'Jatuh tempo 7 hari', 'value' => $this->rupiah((float) $dueSoon->sum('outstandingValue')), 'caption' => $dueSoon->count().' invoice', 'tone' => 'warning'],
                ['label' => 'Overdue', 'value' => $this->rupiah((float) $overdue->sum('outstandingValue')), 'caption' => $overdue->count().' invoice', 'tone' => 'danger'],
            ],
        ]);
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
