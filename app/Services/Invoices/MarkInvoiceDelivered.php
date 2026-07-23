<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MarkInvoiceDelivered
{
    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_PDF_DOWNLOAD = 'pdf_download';

    /**
     * Record a successful delivery and transition the invoice out of draft.
     */
    public function handle(
        Invoice $invoice,
        string $channel,
        ?string $recipient = null,
    ): Invoice {
        if (! in_array($channel, [
            self::CHANNEL_EMAIL,
            self::CHANNEL_PDF_DOWNLOAD,
        ], true)) {
            throw new InvalidArgumentException("Kanal delivery [{$channel}] tidak didukung.");
        }

        return DB::transaction(function () use ($invoice, $channel, $recipient): Invoice {
            $lockedInvoice = Invoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->getKey());
            $deliveredAt = now();
            $metadata = $lockedInvoice->metadata ?? [];
            $events = collect($metadata['delivery_events'] ?? [])
                ->push(array_filter([
                    'channel' => $channel,
                    'recipient' => $recipient,
                    'delivered_at' => $deliveredAt->toISOString(),
                ], fn (mixed $value): bool => $value !== null))
                ->take(-20)
                ->values()
                ->all();

            $metadata['delivery'] = array_filter([
                'last_channel' => $channel,
                'last_recipient' => $recipient,
                'last_delivered_at' => $deliveredAt->toISOString(),
            ], fn (mixed $value): bool => $value !== null);
            $metadata['delivery_events'] = $events;

            $lockedInvoice->forceFill([
                'status' => Invoice::STATUS_SENT,
                'sent_at' => $lockedInvoice->sent_at ?? $deliveredAt,
                'metadata' => $metadata,
            ])->save();

            return $lockedInvoice;
        });
    }
}
