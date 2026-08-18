<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DueInvoicePageController extends Controller
{
    /**
     * Horizon (in days) used to decide which upcoming invoices belong on the follow-up queue.
     */
    private const HORIZON_DAYS = 30;

    /**
     * Horizon (in days) used for the "due soon" summary card, independent of the list horizon.
     */
    private const DUE_SOON_DAYS = 7;

    public function __invoke(): View
    {
        $today = today();
        $until = $today->copy()->addDays(self::HORIZON_DAYS);

        $invoices = Invoice::query()
            ->with(['customer', 'lastFollowUpBy'])
            ->withSum(['payments as verified_paid_amount' => fn ($query) => $query->verified()], 'amount')
            ->receivable()
            ->whereDate('due_date', '<=', $until)
            ->orderBy('due_date')
            ->get();

        $dueInvoices = $invoices
            ->map(fn (Invoice $invoice): array => $this->formatInvoice($invoice, $today))
            ->values();

        $dueSoonCutoff = (int) $today->copy()->addDays(self::DUE_SOON_DAYS)->format('Ymd');

        return view('auth.due-invoices', [
            'dueInvoices' => $dueInvoices,
            'summaryCards' => [
                [
                    'label' => 'Total perlu follow-up',
                    'value' => (string) $dueInvoices->count(),
                ],
                [
                    'label' => 'Overdue',
                    'value' => (string) $dueInvoices->where('status', 'Overdue')->count(),
                    'tone' => 'danger',
                ],
                [
                    'label' => 'Jatuh tempo '.self::DUE_SOON_DAYS.' hari',
                    'value' => (string) $dueInvoices
                        ->where('status', '!=', 'Overdue')
                        ->where('dueSort', '<=', $dueSoonCutoff)
                        ->count(),
                ],
                [
                    'label' => 'Nilai outstanding',
                    'value' => $this->rupiah((float) $dueInvoices->sum('outstandingValue')),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInvoice(Invoice $invoice, Carbon $today): array
    {
        $paid = (float) ($invoice->verified_paid_amount ?? 0);
        $outstanding = max(0, (float) $invoice->total_amount - $paid);
        $dueDate = $invoice->due_date;
        $daysFromDue = (int) $today->diffInDays($dueDate, false);

        $status = match (true) {
            $dueDate->lt($today) || $invoice->payment_status === Invoice::PAYMENT_OVERDUE => 'Overdue',
            $daysFromDue <= 2 => 'Due soon',
            default => 'Scheduled',
        };

        return [
            'invoice' => $invoice->invoice_number,
            'customer' => $invoice->customer?->name ?? 'Pelanggan tidak tersedia',
            'due' => $dueDate->translatedFormat('d M Y'),
            'dueSort' => (int) $dueDate->format('Ymd'),
            'days' => $this->relativeDaysLabel($daysFromDue),
            'amount' => $this->rupiah($outstanding),
            'outstandingValue' => $outstanding,
            'status' => $status,
            'lastFollowUpLabel' => $this->followUpLabel($invoice),
        ];
    }

    private function followUpLabel(Invoice $invoice): string
    {
        if (! $invoice->last_follow_up_at) {
            return 'Belum ada follow-up';
        }

        $when = $invoice->last_follow_up_at->translatedFormat('d M Y, H:i');
        $by = $invoice->lastFollowUpBy?->name ?? 'pengguna terhapus';

        return "{$when} oleh {$by}";
    }

    private function relativeDaysLabel(int $daysFromDue): string
    {
        return match (true) {
            $daysFromDue < 0 => 'Lewat '.abs($daysFromDue).' hari',
            $daysFromDue === 0 => 'Hari ini',
            $daysFromDue === 1 => 'Besok',
            default => "{$daysFromDue} hari lagi",
        };
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
