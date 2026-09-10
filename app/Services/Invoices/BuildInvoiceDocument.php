<?php

namespace App\Services\Invoices;

use App\Models\CompanyProfile;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

/**
 * The one place that decides what the printed invoice says.
 *
 * A stored invoice and an unsaved draft reach the document by different roads —
 * one from the database, one from the create form via CalculateInvoicePreview —
 * and they used to be handed to the template in different shapes. The template
 * then read keys that only one of them had, which is how a preview could look
 * right while the downloaded PDF came out different. Both roads now end here,
 * and both leave through compose(), so the shape cannot drift apart again.
 *
 * This class only presents. Every amount arrives already calculated, by the
 * invoice record or by CalculateInvoiceTotals; nothing here recomputes money.
 */
class BuildInvoiceDocument
{
    public function __construct(
        private readonly SpellRupiah $spellRupiah,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fromInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['customer', 'items', 'creator']);

        return $this->compose(
            number: (string) $invoice->invoice_number,
            statusLabel: $invoice->sent_at ? 'Terkirim' : 'Invoice tersimpan',
            issueDateLabel: $invoice->issue_date?->locale('id')->translatedFormat('j F Y'),
            dueDateLabel: $invoice->due_date?->locale('id')->translatedFormat('j F Y'),
            currency: (string) $invoice->currency,
            sellerName: $invoice->creator?->name,
            customer: [
                'name' => $invoice->customer?->name,
                'address' => $invoice->customer?->address,
                'phone' => $invoice->customer?->phone,
                'email' => $invoice->customer?->email,
            ],
            items: $invoice->items->values()->map(fn ($item): array => [
                'code' => $item->sku,
                'name' => $item->product_name ?: $item->description,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => (float) $item->unit_price,
                'discount_amount' => (float) $item->discount_amount,
                'line_total' => (float) $item->subtotal,
            ])->all(),
            subtotal: (float) $invoice->subtotal,
            discountType: $invoice->discount_type,
            discountValue: (float) $invoice->discount_value,
            discountAmount: (float) $invoice->discount_amount,
            taxRate: (float) $invoice->tax_rate,
            taxAmount: (float) $invoice->tax_amount,
            shippingCost: (float) $invoice->shipping_cost,
            isFreeShipping: (bool) $invoice->is_free_shipping,
            shippingType: $invoice->shipping_type,
            totalAmount: (float) $invoice->total_amount,
            dpRequiredPercent: (float) $invoice->dp_required_percent,
            dpAmount: $invoice->requiredDpAmount(),
            designNotes: $invoice->design_notes,
            customerNotes: $invoice->notes,
            terms: $invoice->terms,
        );
    }

    /**
     * @param  array<string, mixed>  $preview  Output of CalculateInvoicePreview.
     * @return array<string, mixed>
     */
    public function fromPreview(array $preview): array
    {
        $customer = $preview['customer'] ?? [];

        return $this->compose(
            number: (string) ($preview['invoice_number'] ?? ''),
            statusLabel: (string) ($preview['status_label'] ?? 'Draft'),
            issueDateLabel: $preview['issue_date_label'] ?? null,
            dueDateLabel: $preview['due_date_label'] ?? null,
            currency: (string) ($preview['currency'] ?? 'IDR'),
            sellerName: $preview['seller_name'] ?? null,
            customer: [
                'name' => $customer['name'] ?? null,
                'address' => $customer['address'] ?? null,
                'phone' => $customer['phone'] ?? null,
                'email' => $customer['email'] ?? null,
            ],
            items: collect($preview['items'] ?? [])->values()->map(fn (array $item): array => [
                // The draft form sends "name"; a stored line sends
                // "product_name". Both are accepted here so neither road has to
                // know about the other's vocabulary.
                'code' => $item['code'] ?? $item['sku'] ?? null,
                'name' => ($item['product_name'] ?? '') ?: ($item['name'] ?? ''),
                'description' => $item['description'] ?? null,
                'quantity' => (float) ($item['quantity'] ?? 0),
                'unit' => $item['unit'] ?? null,
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'discount_amount' => (float) ($item['discount_amount'] ?? 0),
                'line_total' => (float) ($item['line_total'] ?? 0),
            ])->all(),
            subtotal: (float) ($preview['subtotal'] ?? 0),
            discountType: $preview['discount_type'] ?? null,
            discountValue: (float) ($preview['discount_value'] ?? 0),
            discountAmount: (float) ($preview['discount_amount'] ?? 0),
            taxRate: (float) ($preview['tax_rate'] ?? 0),
            taxAmount: (float) ($preview['tax_amount'] ?? 0),
            shippingCost: (float) ($preview['shipping_cost'] ?? 0),
            isFreeShipping: (bool) ($preview['is_free_shipping'] ?? false),
            shippingType: $preview['shipping_type'] ?? null,
            totalAmount: (float) ($preview['total_amount'] ?? 0),
            dpRequiredPercent: (float) ($preview['dp_required_percent'] ?? 0),
            dpAmount: (float) ($preview['dp_amount'] ?? 0),
            designNotes: $preview['design_notes'] ?? null,
            customerNotes: $preview['notes'] ?? null,
            terms: $preview['terms'] ?? null,
        );
    }

    /**
     * @param  array<string, string|null>  $customer
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function compose(
        string $number,
        string $statusLabel,
        ?string $issueDateLabel,
        ?string $dueDateLabel,
        string $currency,
        ?string $sellerName,
        array $customer,
        array $items,
        float $subtotal,
        ?string $discountType,
        float $discountValue,
        float $discountAmount,
        float $taxRate,
        float $taxAmount,
        float $shippingCost,
        bool $isFreeShipping,
        ?string $shippingType,
        float $totalAmount,
        float $dpRequiredPercent,
        float $dpAmount,
        ?string $designNotes,
        ?string $customerNotes,
        ?string $terms,
    ): array {
        return [
            'company' => $this->company(),
            'number' => $number,
            'status_label' => $statusLabel,
            'issue_date_label' => $this->blankToNull($issueDateLabel),
            'due_date_label' => $this->blankToNull($dueDateLabel),
            'currency' => $currency,
            'seller_name' => $this->blankToNull($sellerName),
            'shipping_label' => $this->shippingLabel($shippingType, $shippingCost, $isFreeShipping),

            'customer' => [
                'name' => $this->blankToNull($customer['name'] ?? null),
                'address' => $this->blankToNull($customer['address'] ?? null),
                'phone' => $this->blankToNull($customer['phone'] ?? null),
                'email' => $this->blankToNull($customer['email'] ?? null),
            ],

            'items' => array_map(fn (array $item): array => [
                'code' => $this->blankToNull($item['code'] ?? null),
                'name' => (string) ($item['name'] ?? ''),
                'description' => $this->blankToNull($item['description'] ?? null),
                'quantity' => (float) $item['quantity'],
                'quantity_label' => $this->quantityLabel((float) $item['quantity']),
                'unit' => $this->blankToNull($item['unit'] ?? null),
                'unit_price' => (float) $item['unit_price'],
                'discount_amount' => (float) $item['discount_amount'],
                'line_total' => (float) $item['line_total'],
            ], $items),

            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'shipping_cost' => $shippingCost,
            'is_free_shipping' => $isFreeShipping,
            'total_amount' => $totalAmount,
            'total_in_words' => $this->spellRupiah->spell($totalAmount),

            'dp_required_percent' => $dpRequiredPercent,
            'dp_amount' => $dpAmount,

            // Kept apart all the way to the template. One is for the workshop,
            // the other is addressed to the customer, and merging them once
            // would be enough to print an internal instruction on a document
            // that goes out.
            'design_notes' => $this->blankToNull($designNotes),
            'customer_notes' => $this->blankToNull($customerNotes),
            'terms' => $this->blankToNull($terms),
        ];
    }

    /**
     * The company block, also used by the on-screen preview so that page and
     * the printed document name the same business and the same bank account.
     *
     * @return array<string, string|null>
     */
    public function company(): array
    {
        $profile = CompanyProfile::query()->where('is_default', true)->first();

        $address = collect([
            $profile?->address,
            $profile?->city,
            $profile?->province,
            $profile?->postal_code,
        ])->map(fn ($part) => $this->blankToNull($part))->filter()->join(', ');

        return [
            'name' => $this->blankToNull($profile?->business_name) ?? 'YokPrinting.ID',
            'legal_name' => $this->blankToNull($profile?->legal_name),
            'address' => $this->blankToNull($address),
            'phone' => $this->blankToNull($profile?->phone),
            'email' => $this->blankToNull($profile?->email),
            'website' => $this->blankToNull($profile?->website),
            'tax_number' => $this->blankToNull($profile?->tax_number),
            'bank_name' => $this->blankToNull($profile?->bank_name),
            'bank_account' => $this->blankToNull($profile?->bank_account),
            'bank_holder' => $this->blankToNull($profile?->bank_holder),
            'logo_data_uri' => $this->logoDataUri($profile),
        ];
    }

    /**
     * Dompdf has remote loading disabled, so the logo has to travel inside the
     * HTML. Falls back to the bundled mark when no profile logo is set, and to
     * nothing at all if neither file is readable - a missing logo must not stop
     * an invoice printing.
     */
    private function logoDataUri(?CompanyProfile $profile): ?string
    {
        $candidates = [];

        if ($profile?->logo_path) {
            $candidates[] = Storage::disk('public')->path($profile->logo_path);
        }

        $candidates[] = public_path('images/yokprinting-logo.png');

        foreach ($candidates as $path) {
            if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $contents = @file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                default => 'image/png',
            };

            return "data:{$mime};base64,".base64_encode($contents);
        }

        return null;
    }

    private function shippingLabel(?string $shippingType, float $shippingCost, bool $isFreeShipping): ?string
    {
        if ($isFreeShipping) {
            return 'Gratis ongkir';
        }

        return match ($shippingType) {
            Invoice::SHIPPING_PAID_BY_CUSTOMER => 'Ongkir dibayar pelanggan',
            Invoice::SHIPPING_COMPANY_FREE_SHIPPING => 'Gratis ongkir',
            default => $shippingCost > 0 ? 'Ongkir dibayar pelanggan' : null,
        };
    }

    private function quantityLabel(float $quantity): string
    {
        $formatted = number_format($quantity, 4, ',', '.');

        // Quantities are stored with four decimals but are whole numbers in
        // practice; trailing zeroes would read as false precision on a printed
        // document.
        if (str_contains($formatted, ',')) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : $value;
    }
}
