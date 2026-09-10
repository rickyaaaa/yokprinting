<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\BuildInvoiceDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acceptance for the printed invoice: the fixture, wording and totals are the
 * ones the client specified.
 */
class InvoiceDocumentLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prints_the_client_fixture_with_correct_names_codes_and_totals(): void
    {
        $html = $this->render($this->fixtureInvoice());

        // Product names, not the generated spec description.
        $this->assertStringContainsString('Cup PP 12Oz Datar 7GR ASB', $html);
        $this->assertStringContainsString('Tutup Strawless D93', $html);

        // Item codes stay off the customer's copy, so the reference layout's
        // Kode Barang column is not reproduced.
        $this->assertStringNotContainsString('100030', $html);
        $this->assertStringNotContainsString('100121', $html);
        $this->assertStringNotContainsString('Kode Barang', $html);

        // Line amounts, then the summary.
        $this->assertStringContainsString('1.440.000', $html);
        $this->assertStringContainsString('600.000', $html);
        $this->assertStringContainsString('Rp2.040.000', $html);

        $this->assertStringContainsString('Dua juta empat puluh ribu rupiah', $html);
        $this->assertStringContainsString('PT Testing', $html);
    }

    public function test_it_labels_the_two_kinds_of_note_separately_and_does_not_swap_them(): void
    {
        $html = $this->render($this->fixtureInvoice());

        $this->assertStringContainsString('Catatan desain/produksi:', $html);
        $this->assertStringContainsString('Cetak 1 warna putih', $html);
        $this->assertStringContainsString('Catatan untuk pelanggan:', $html);
        $this->assertStringContainsString('Mohon cek kembali spesifikasi pesanan.', $html);

        // Each note has to sit under its own heading. Comparing positions
        // catches the failure that matters here - the labels being present but
        // attached to the wrong text.
        $designLabel = strpos($html, 'Catatan desain/produksi:');
        $designText = strpos($html, 'Cetak 1 warna putih');
        $customerLabel = strpos($html, 'Catatan untuk pelanggan:');
        $customerText = strpos($html, 'Mohon cek kembali spesifikasi pesanan.');

        $this->assertGreaterThan($designLabel, $designText);
        $this->assertLessThan($customerLabel, $designText);
        $this->assertGreaterThan($customerLabel, $customerText);
    }

    public function test_it_renders_every_section_of_the_reference_layout(): void
    {
        $html = $this->render($this->fixtureInvoice());

        foreach ([
            'INVOICE',            // header
            'KEPADA',             // customer block
            'Tanggal',            // invoice info block
            'Nomor',
            'Jatuh Tempo',
            'Nama Barang',        // item table
            'Total Harga',
            'Terbilang',
            'KETERANGAN',
            'Sub Total',          // totals
            'Total',
            'Disetujui,',         // approval
        ] as $section) {
            $this->assertStringContainsString($section, $html, "Missing layout section: {$section}");
        }
    }

    public function test_company_identity_and_bank_details_come_from_the_profile(): void
    {
        CompanyProfile::query()->create([
            'business_name' => 'Contoh Percetakan',
            'address' => 'Jl. Contoh No. 1',
            'city' => 'Tangerang',
            'phone' => '+62 800 0000',
            'bank_name' => 'Bank Contoh',
            'bank_account' => '123-456-7890',
            'bank_holder' => 'PT Contoh Percetakan',
            'is_default' => true,
        ]);

        $html = $this->render($this->fixtureInvoice());

        $this->assertStringContainsString('Contoh Percetakan', $html);
        $this->assertStringContainsString('Jl. Contoh No. 1, Tangerang', $html);
        $this->assertStringContainsString('123-456-7890', $html);
        $this->assertStringContainsString('Bank Contoh', $html);

        // The values the template used to hardcode must be gone.
        $this->assertStringNotContainsString('012 345 6789', $html);
        $this->assertStringNotContainsString('Bank Central Asia', $html);
    }

    /**
     * Phase 12: the draft preview and the saved invoice must describe the same
     * transaction identically. Both are normalised by the same builder, so the
     * comparison is on the data each produces.
     */
    public function test_preview_and_stored_invoice_produce_identical_document_data(): void
    {
        $invoice = $this->fixtureInvoice();
        $builder = app(BuildInvoiceDocument::class);

        $stored = $builder->fromInvoice($invoice);
        $draft = $builder->fromPreview([
            'invoice_number' => 'INV/2026/0052',
            'issue_date_label' => $stored['issue_date_label'],
            'due_date_label' => $stored['due_date_label'],
            'currency' => 'IDR',
            'customer' => [
                'name' => 'PT Testing',
                'address' => 'Jl. Uji Coba No. 10, Jakarta',
                'phone' => '+62 812 0000 0000',
                'email' => 'po@pttesting.co.id',
            ],
            'items' => [
                [
                    'name' => 'Cup PP 12Oz Datar 7GR ASB',
                    'sku' => '100030',
                    'quantity' => 3000,
                    'unit_price' => 480,
                    'line_total' => 1_440_000,
                ],
                [
                    'name' => 'Tutup Strawless D93',
                    'sku' => '100121',
                    'quantity' => 3000,
                    'unit_price' => 200,
                    'line_total' => 600_000,
                ],
            ],
            'subtotal' => 2_040_000,
            'total_amount' => 2_040_000,
            'design_notes' => 'Cetak 1 warna putih',
            'notes' => 'Mohon cek kembali spesifikasi pesanan.',
        ]);

        foreach (['subtotal', 'discount_amount', 'tax_amount', 'shipping_cost', 'total_amount', 'total_in_words', 'design_notes', 'customer_notes'] as $key) {
            $this->assertSame($stored[$key], $draft[$key], "Mismatch on {$key}");
        }

        $this->assertSame($stored['customer'], $draft['customer']);

        foreach (['code', 'name', 'quantity', 'quantity_label', 'unit_price', 'line_total'] as $key) {
            $this->assertSame(
                array_column($stored['items'], $key),
                array_column($draft['items'], $key),
                "Item mismatch on {$key}",
            );
        }
    }

    public function test_the_seller_is_the_business_not_the_operator_who_saved_it(): void
    {
        CompanyProfile::query()->create([
            'business_name' => 'YokPrinting',
            'is_default' => true,
        ]);

        $invoice = $this->fixtureInvoice();
        $invoice->update([
            'created_by' => User::factory()->create(['name' => 'Admin YokPrinting'])->id,
        ]);

        $html = $this->render($invoice->fresh(['customer', 'items']));

        $this->assertStringContainsString('Penjual', $html);
        $this->assertStringContainsString('YokPrinting', $html);

        // The operator account name never reaches a document a customer reads.
        $this->assertStringNotContainsString('Admin YokPrinting', $html);
    }

    public function test_free_shipping_reads_the_same_in_both_places(): void
    {
        $invoice = $this->fixtureInvoice();
        $invoice->update([
            'is_free_shipping' => true,
            'shipping_cost' => 25_000,
            'shipping_type' => Invoice::SHIPPING_COMPANY_FREE_SHIPPING,
        ]);

        $html = $this->render($invoice->fresh(['customer', 'items']));

        // Once in the Pengiriman row, once in the totals - the same wording
        // both times, which is the whole point of the rename.
        $this->assertSame(2, substr_count($html, 'Free Ongkir'));
        $this->assertStringNotContainsString('Gratis ongkir', $html);
        $this->assertStringNotContainsString('Ongkir (gratis)', $html);
    }

    public function test_the_internal_status_label_is_not_printed(): void
    {
        $html = $this->render($this->fixtureInvoice());

        $this->assertStringContainsString('INVOICE', $html);
        $this->assertStringNotContainsString('Invoice tersimpan', $html);
        $this->assertStringNotContainsString('Terkirim', $html);
    }

    /**
     * Phase 13: an invoice written before any of these fields existed still has
     * to print.
     */
    public function test_an_older_invoice_without_optional_data_still_renders(): void
    {
        $customer = Customer::query()->create(['code' => 'CUS-OLD', 'name' => 'Pelanggan Lama']);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV/2025/0001',
            'issue_date' => '2025-01-05',
            'due_date' => '2025-01-19',
            'subtotal' => 100_000,
            'total_amount' => 100_000,
        ]);
        $invoice->items()->create([
            'product_name' => 'Cetak Stiker',
            'quantity' => 1,
            'unit_price' => 100_000,
            'subtotal' => 100_000,
            'total_amount' => 100_000,
        ]);

        $html = $this->render($invoice);

        $this->assertStringContainsString('Pelanggan Lama', $html);
        $this->assertStringContainsString('Cetak Stiker', $html);
        $this->assertStringContainsString('Seratus ribu rupiah', $html);

        // Nothing to say, so nothing is printed.
        $this->assertStringNotContainsString('Catatan desain/produksi:', $html);
        $this->assertStringNotContainsString('Catatan untuk pelanggan:', $html);
    }

    private function render(Invoice $invoice): string
    {
        return view('pdf.invoices.preview', [
            'document' => app(BuildInvoiceDocument::class)->fromInvoice($invoice),
        ])->render();
    }

    private function fixtureInvoice(): Invoice
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-TEST',
            'name' => 'PT Testing',
            'email' => 'po@pttesting.co.id',
            'phone' => '+62 812 0000 0000',
            'address' => 'Jl. Uji Coba No. 10, Jakarta',
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV/2026/0052',
            'issue_date' => '2026-09-09',
            'due_date' => '2026-09-15',
            'subtotal' => 2_040_000,
            'total_amount' => 2_040_000,
            'design_notes' => 'Cetak 1 warna putih',
            'notes' => 'Mohon cek kembali spesifikasi pesanan.',
        ]);

        $invoice->items()->createMany([
            [
                'product_name' => 'Cup PP 12Oz Datar 7GR ASB',
                'sku' => '100030',
                'quantity' => 3000,
                'unit_price' => 480,
                'subtotal' => 1_440_000,
                'total_amount' => 1_440_000,
            ],
            [
                'product_name' => 'Tutup Strawless D93',
                'sku' => '100121',
                'quantity' => 3000,
                'unit_price' => 200,
                'subtotal' => 600_000,
                'total_amount' => 600_000,
            ],
        ]);

        return $invoice->fresh(['customer', 'items']);
    }
}
