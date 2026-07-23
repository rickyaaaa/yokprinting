<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesReportDemoSeeder extends Seeder
{
    /**
     * Seed invoice, item, and payment demo data for dashboard and sales reports.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $customers = $this->seedCustomers();
            $products = $this->seedProducts();

            $this->seedInvoices($customers, $products);
        });
    }

    /**
     * @return array<string, Customer>
     */
    private function seedCustomers(): array
    {
        $rows = [
            'CUS-001' => ['name' => 'PT Sinar Nusantara', 'email' => 'finance@sinarnusantara.co.id', 'phone' => '+62 21 555 0198', 'city' => 'Jakarta Selatan'],
            'CUS-002' => ['name' => 'CV Lautan Rasa', 'email' => 'billing@lautanrasa.example', 'phone' => '+62 361 700 210', 'city' => 'Denpasar'],
            'CUS-003' => ['name' => 'PT Bumi Lestari', 'email' => 'finance@bumilestari.example', 'phone' => '+62 22 7788 440', 'city' => 'Bandung'],
            'CUS-004' => ['name' => 'PT Cakra Media', 'email' => 'ap@cakramedia.example', 'phone' => '+62 31 811 040', 'city' => 'Surabaya'],
            'CUS-005' => ['name' => 'UD Sumber Makmur', 'email' => 'owner@sumbermakmur.example', 'phone' => '+62 274 889 441', 'city' => 'Yogyakarta'],
        ];

        return collect($rows)
            ->mapWithKeys(fn (array $row, string $code): array => [
                $code => Customer::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        ...$row,
                        'address' => 'Alamat demo '.$row['city'],
                        'province' => 'Indonesia',
                        'postal_code' => '10000',
                        'status' => Customer::STATUS_ACTIVE,
                    ],
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, Product>
     */
    private function seedProducts(): array
    {
        $rows = [
            'JSA-BRAND-01' => ['name' => 'Paket desain brand refresh', 'category' => 'Jasa desain', 'price' => 12000000],
            'PRN-CATALOG-01' => ['name' => 'Cetak katalog premium', 'category' => 'Cetak premium', 'price' => 6000000],
            'JSA-WEB-03' => ['name' => 'Website company profile', 'category' => 'Jasa desain', 'price' => 8750000],
            'PRM-FLYER-01' => ['name' => 'Flyer promosi bulanan', 'category' => 'Materi promosi', 'price' => 7900000],
        ];

        return collect($rows)
            ->mapWithKeys(fn (array $row, string $sku): array => [
                $sku => Product::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        ...$row,
                        'description' => 'Produk demo untuk pengujian laporan.',
                        'unit' => 'paket',
                        'track_stock' => false,
                        'status' => Product::STATUS_ACTIVE,
                    ],
                ),
            ])
            ->all();
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  array<string, Product>  $products
     */
    private function seedInvoices(array $customers, array $products): void
    {
        $rows = [
            [
                'invoice_number' => 'INV-2026-0084',
                'customer' => 'CUS-001',
                'issue_date' => '2026-07-23',
                'due_date' => '2026-07-30',
                'payment_status' => Invoice::PAYMENT_PARTIAL,
                'subtotal' => 18000000,
                'discount_amount' => 1530000,
                'tax_amount' => 1980000,
                'total_amount' => 18450000,
                'items' => [
                    ['sku' => 'JSA-BRAND-01', 'quantity' => 1, 'unit_price' => 12000000],
                    ['sku' => 'PRN-CATALOG-01', 'quantity' => 1, 'unit_price' => 6000000],
                ],
                'payments' => [
                    ['payment_number' => 'PAY-20260724-0001', 'payment_date' => '2026-07-24', 'amount' => 8000000, 'method' => Payment::METHOD_TRANSFER_BCA, 'reference' => 'BCA-77219'],
                    ['payment_number' => 'PAY-20260726-0002', 'payment_date' => '2026-07-26', 'amount' => 4000000, 'method' => Payment::METHOD_TRANSFER_BCA, 'reference' => 'BCA-77302'],
                ],
            ],
            [
                'invoice_number' => 'INV-2026-0082',
                'customer' => 'CUS-002',
                'issue_date' => '2026-07-20',
                'due_date' => '2026-08-02',
                'payment_status' => Invoice::PAYMENT_UNPAID,
                'subtotal' => 12750000,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 12750000,
                'items' => [
                    ['sku' => 'PRN-CATALOG-01', 'quantity' => 1, 'unit_price' => 12750000],
                ],
                'payments' => [],
            ],
            [
                'invoice_number' => 'INV-2026-0078',
                'customer' => 'CUS-003',
                'issue_date' => '2026-07-10',
                'due_date' => '2026-07-20',
                'payment_status' => Invoice::PAYMENT_UNPAID,
                'subtotal' => 5600000,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 5600000,
                'items' => [
                    ['sku' => 'JSA-WEB-03', 'quantity' => 1, 'unit_price' => 5600000],
                ],
                'payments' => [],
            ],
            [
                'invoice_number' => 'INV-2026-0076',
                'customer' => 'CUS-004',
                'issue_date' => '2026-07-08',
                'due_date' => '2026-07-22',
                'payment_status' => Invoice::PAYMENT_PARTIAL,
                'subtotal' => 14800000,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 14800000,
                'items' => [
                    ['sku' => 'PRN-CATALOG-01', 'quantity' => 2, 'unit_price' => 7400000],
                ],
                'payments' => [
                    ['payment_number' => 'PAY-20260726-0003', 'payment_date' => '2026-07-26', 'amount' => 4250000, 'method' => Payment::METHOD_TRANSFER_MANDIRI, 'reference' => 'MDR-92118'],
                ],
            ],
            [
                'invoice_number' => 'INV-2026-0075',
                'customer' => 'CUS-002',
                'issue_date' => '2026-07-06',
                'due_date' => '2026-07-20',
                'payment_status' => Invoice::PAYMENT_PAID,
                'subtotal' => 12750000,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 12750000,
                'items' => [
                    ['sku' => 'PRM-FLYER-01', 'quantity' => 1, 'unit_price' => 12750000],
                ],
                'payments' => [
                    ['payment_number' => 'PAY-20260723-0004', 'payment_date' => '2026-07-23', 'amount' => 12750000, 'method' => Payment::METHOD_TRANSFER_BCA, 'reference' => 'BCA-77190'],
                ],
            ],
        ];

        foreach ($rows as $row) {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->updateOrCreate(
                ['invoice_number' => $row['invoice_number']],
                [
                    'customer_id' => $customers[$row['customer']]->getKey(),
                    'issue_date' => $row['issue_date'],
                    'due_date' => $row['due_date'],
                    'status' => Invoice::STATUS_SENT,
                    'payment_status' => $row['payment_status'],
                    'currency' => 'IDR',
                    'subtotal' => $row['subtotal'],
                    'discount_type' => 'fixed',
                    'discount_value' => $row['discount_amount'],
                    'discount_amount' => $row['discount_amount'],
                    'tax_rate' => $row['tax_amount'] > 0 ? 11 : 0,
                    'tax_amount' => $row['tax_amount'],
                    'total_amount' => $row['total_amount'],
                    'notes' => 'Data demo untuk pengujian laporan penjualan.',
                    'terms' => 'Pembayaran sesuai tanggal jatuh tempo.',
                    'metadata' => ['source' => 'sales-report-demo-seeder'],
                    'paid_at' => $row['payment_status'] === Invoice::PAYMENT_PAID ? '2026-07-23 10:00:00' : null,
                ],
            );

            $invoice->items()->delete();

            foreach ($row['items'] as $index => $item) {
                $product = $products[$item['sku']];
                $lineSubtotal = $item['quantity'] * $item['unit_price'];

                $invoice->items()->create([
                    'product_id' => $product->getKey(),
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'description' => $product->description,
                    'quantity' => $item['quantity'],
                    'unit' => $product->unit,
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $lineSubtotal,
                    'total_amount' => $lineSubtotal,
                    'sort_order' => $index + 1,
                ]);
            }

            foreach ($row['payments'] as $payment) {
                $invoice->payments()->updateOrCreate(
                    ['payment_number' => $payment['payment_number']],
                    [
                        'payment_date' => $payment['payment_date'],
                        'method' => $payment['method'],
                        'reference' => $payment['reference'],
                        'currency' => 'IDR',
                        'amount' => $payment['amount'],
                        'status' => Payment::STATUS_VERIFIED,
                        'notes' => 'Pembayaran demo.',
                        'metadata' => ['source' => 'sales-report-demo-seeder'],
                        'verified_at' => $payment['payment_date'].' 10:00:00',
                    ],
                );
            }
        }
    }
}
