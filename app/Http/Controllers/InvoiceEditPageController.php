<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Contracts\View\View;

class InvoiceEditPageController extends Controller
{
    /**
     * Show the invoice edit form, seeded with the invoice's current data in
     * the exact shape the create form's draft-restore mechanism expects
     * (see resources/js/app.js: invoiceDraftForm/customerPicker/invoiceItems
     * restoreSavedEditorDraft/restoreSelectedCustomer/restoreItemsFromSavedDraft).
     */
    public function __invoke(Invoice $invoice): View
    {
        abort_unless(
            $invoice->isEditable(),
            403,
            'Invoice yang sudah dikirim/dibatalkan tidak bisa diedit. Batalkan lalu buat invoice baru kalau item/harga perlu berubah.',
        );

        $invoice->load([
            'customer',
            'items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        return view('invoices.edit', [
            'invoiceModel' => $invoice,
            'draftPayload' => $this->buildDraftPayload($invoice),
        ]);
    }

    /** @return array<string, mixed> */
    private function buildDraftPayload(Invoice $invoice): array
    {
        return [
            'customer_id' => $invoice->customer_id,
            'customer_name' => $invoice->customer?->name ?? '',
            'customer_email' => $invoice->customer?->email ?? '',
            'customer_phone' => $invoice->customer?->phone ?? '',
            'customer_address' => $invoice->customer?->address ?? '',
            'invoice_number' => $invoice->invoice_number,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'items' => $invoice->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
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
                'price' => (float) $item->unit_price,
            ])->values(),
            'discount' => [
                'type' => $invoice->discount_type ?? 'percentage',
                'value' => (float) $invoice->discount_value,
            ],
            'tax' => [
                // Not stored separately - a zero rate at save time always
                // meant tax was off, so this reconstruction is exact.
                'enabled' => (float) $invoice->tax_rate > 0,
                'rate' => (float) $invoice->tax_rate,
            ],
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
            'production_status' => $invoice->production_status,
            'shipping_cost' => (float) $invoice->shipping_cost,
            'is_free_shipping' => (bool) $invoice->is_free_shipping,
            'design_notes' => $invoice->design_notes,
            'dp_required_percent' => (float) $invoice->dp_required_percent,
        ];
    }
}
