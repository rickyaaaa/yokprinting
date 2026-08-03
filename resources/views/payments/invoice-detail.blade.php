@php
    $invoice = [
        'number' => 'INV-2026-0084',
        'status' => 'Menunggu pembayaran',
        'customer' => 'PT Sinar Nusantara',
        'email' => 'finance@sinarnusantara.co.id',
        'phone' => '+62 812 9900 1188',
        'address' => 'Jl. Jenderal Sudirman No. 88, Jakarta Selatan',
        'issued_at' => '23 Juli 2026',
        'due_at' => '30 Juli 2026',
        'terms' => 'Net 7',
        'subtotal' => 'Rp18.000.000',
        'tax' => 'Rp1.980.000',
        'discount' => '- Rp1.530.000',
        'total' => 'Rp18.450.000',
        'paid' => 'Rp12.000.000',
        'remaining' => 'Rp6.450.000',
        'progress' => 65,
        'production_status' => 'ACC Mockup/Desain',
        'dp_required' => 'Rp9.225.000',
        'design_notes' => 'Logo tengah, tinta hitam pekat, mockup sudah dikirim ke customer untuk ACC final.',
        'mockup_url' => 'https://yokprinting.id/mockup/INV-2026-0084',
    ];

    $items = [
        ['name' => 'Sablon Cup 16 Oz Oval (8gr)', 'spec' => 'Tinta Hitam · 2 Sisi · MOQ 1.000 pcs', 'quantity' => '10.000 pcs', 'price' => 'Rp850', 'total' => 'Rp8.500.000'],
        ['name' => 'Sablon Cup 12 Oz Datar (7gr)', 'spec' => 'Tinta Putih · 1 Sisi · MOQ 1.000 pcs', 'quantity' => '8.000 pcs', 'price' => 'Rp700', 'total' => 'Rp5.600.000'],
        ['name' => 'Dus Kemasan Cup 16 Oz', 'spec' => 'Packing pengiriman · kelipatan 10 dus', 'quantity' => '200 dus', 'price' => 'Rp19.500', 'total' => 'Rp3.900.000'],
    ];

    $productionSteps = [
        'Drafting',
        'Menunggu DP',
        'ACC Mockup/Desain',
        'Proses Sablon/Cetak',
        'Siap Diambil/Kirim',
        'Lunas & Selesai',
    ];

    $currentProductionIndex = array_search($invoice['production_status'], $productionSteps, true);

    $waMessage = implode("\n", [
        "Halo {$invoice['customer']},",
        '',
        "Berikut invoice dari YokPrinting.ID:",
        "Invoice: {$invoice['number']}",
        "Total tagihan: {$invoice['total']}",
        "DP/pembayaran diterima: {$invoice['paid']}",
        "Sisa pelunasan: {$invoice['remaining']}",
        "Link invoice: http://127.0.0.1:8000/invoices/preview",
        '',
        'Mohon konfirmasi pembayaran/ACC desain agar produksi bisa kami lanjutkan. Terima kasih.',
    ]);

    $waLink = 'https://wa.me/6281299001188?text='.rawurlencode($waMessage);

    $payments = [
        ['date' => '24 Juli 2026', 'method' => 'Transfer BCA', 'reference' => 'BCA-77219', 'amount' => 'Rp8.000.000', 'status' => 'Terverifikasi'],
        ['date' => '26 Juli 2026', 'method' => 'Transfer BCA', 'reference' => 'BCA-77302', 'amount' => 'Rp4.000.000', 'status' => 'Terverifikasi'],
    ];

    $paymentMethods = [
        ['label' => 'Bank', 'value' => 'Bank Central Asia'],
        ['label' => 'No. rekening', 'value' => '012 345 6789'],
        ['label' => 'Atas nama', 'value' => 'PT Ruang Karya Digital'],
    ];
@endphp

@php
    $formatRupiah = static fn (float $amount): string => 'Rp'.number_format($amount, 0, ',', '.');
    $formatDate = static fn ($date): string => $date?->locale('id')->translatedFormat('j F Y') ?? '-';
    $paidAmount = $invoiceModel->verifiedPaidAmount();
    $remainingAmount = $invoiceModel->remainingAmount();
    $totalAmount = (float) $invoiceModel->total_amount;
    $paymentProgress = $totalAmount > 0
        ? min(100, round(($paidAmount / $totalAmount) * 100))
        : 0;
    $isOverdue = $invoiceModel->due_date?->isPast()
        && $invoiceModel->payment_status !== \App\Models\Invoice::PAYMENT_PAID;
    $effectivePaymentStatus = $isOverdue
        ? \App\Models\Invoice::PAYMENT_OVERDUE
        : $invoiceModel->payment_status;
    $paymentStatusLabel = match ($effectivePaymentStatus) {
        \App\Models\Invoice::PAYMENT_PAID => 'Lunas',
        \App\Models\Invoice::PAYMENT_PARTIAL => 'Pembayaran parsial',
        \App\Models\Invoice::PAYMENT_OVERDUE => 'Jatuh tempo',
        default => 'Menunggu pembayaran',
    };

    $invoice = [
        'number' => $invoiceModel->invoice_number,
        'status' => $paymentStatusLabel,
        'customer' => $invoiceModel->customer?->name ?? 'Pelanggan tidak tersedia',
        'email' => $invoiceModel->customer?->email,
        'phone' => $invoiceModel->customer?->phone,
        'address' => collect([
            $invoiceModel->customer?->address,
            $invoiceModel->customer?->city,
            $invoiceModel->customer?->province,
        ])->filter()->implode(', '),
        'issued_at' => $formatDate($invoiceModel->issue_date),
        'due_at' => $formatDate($invoiceModel->due_date),
        'terms' => $invoiceModel->terms ?: '-',
        'subtotal' => $formatRupiah((float) $invoiceModel->subtotal),
        'tax' => $formatRupiah((float) $invoiceModel->tax_amount),
        'discount' => '- '.$formatRupiah((float) $invoiceModel->discount_amount),
        'total' => $formatRupiah($totalAmount),
        'paid' => $formatRupiah($paidAmount),
        'remaining' => $formatRupiah($remainingAmount),
        'remaining_amount' => $remainingAmount,
        'progress' => $paymentProgress,
        'payment_status' => $effectivePaymentStatus,
        'production_status' => $invoiceModel->productionStatusLabel(),
        'production_status_key' => $invoiceModel->production_status,
        'dp_required' => $formatRupiah($invoiceModel->requiredDpAmount()),
        'design_notes' => $invoiceModel->design_notes ?: 'Belum ada catatan desain.',
        'mockup_url' => $invoiceModel->mockup_url,
    ];

    $items = $invoiceModel->items->map(static function ($item) use ($formatRupiah): array {
        $quantity = rtrim(rtrim(number_format((float) $item->quantity, 4, ',', '.'), '0'), ',');

        return [
            'name' => $item->product_name,
            'spec' => $item->description ?: collect([
                $item->cup_size,
                $item->cup_model,
                $item->grammage,
                $item->screen_printing_color ? "Tinta {$item->screen_printing_color}" : null,
                $item->jenis_cetak,
            ])->filter()->implode(' · '),
            'quantity' => trim("{$quantity} {$item->unit}"),
            'price' => $formatRupiah((float) $item->unit_price),
            'total' => $formatRupiah((float) $item->total_amount),
        ];
    });

    $productionSteps = \App\Models\Invoice::productionWorkflow();
    $currentProductionIndex = collect($productionSteps)
        ->search(fn (array $step): bool => $step['key'] === $invoice['production_status_key']);

    $waMessage = implode("\n", [
        "Halo {$invoice['customer']},",
        '',
        'Berikut invoice dari YokPrinting.ID:',
        "Invoice: {$invoice['number']}",
        "Total tagihan: {$invoice['total']}",
        "DP/pembayaran diterima: {$invoice['paid']}",
        "Sisa pelunasan: {$invoice['remaining']}",
        '',
        'Mohon konfirmasi pembayaran/ACC desain agar produksi bisa kami lanjutkan. Terima kasih.',
    ]);

    $waNumber = preg_replace('/\D+/', '', $invoice['phone'] ?? '') ?: '';
    $waNumber = str_starts_with($waNumber, '0') ? '62'.substr($waNumber, 1) : $waNumber;
    $waLink = $waNumber !== '' ? 'https://wa.me/'.$waNumber.'?text='.rawurlencode($waMessage) : null;

    $payments = $invoiceModel->payments->map(static fn ($payment): array => [
        'date' => $formatDate($payment->payment_date),
        'method' => $payment->methodLabel(),
        'reference' => $payment->reference ?: '-',
        'amount' => $formatRupiah((float) $payment->amount),
        'status' => $payment->statusLabel(),
    ]);
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Detail invoice dan pembayaran YokPrinting.ID">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Detail {{ $invoice['number'] }} - YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div
            class="min-h-screen lg:flex"
            x-data="{ sidebarOpen: false }"
            @keydown.escape.window="sidebarOpen = false"
        >
            <div
                class="fixed inset-0 z-30 bg-ink/45 lg:hidden"
                x-cloak
                x-show="sidebarOpen"
                x-transition.opacity
                @click="sidebarOpen = false"
                aria-hidden="true"
            ></div>

            <x-app-sidebar />

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button
                        type="button"
                        class="mr-3 rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800 lg:hidden"
                        @click="sidebarOpen = true"
                        aria-controls="app-sidebar"
                        :aria-expanded="sidebarOpen"
                        aria-label="Buka navigasi"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <a href="{{ route('dashboard') }}" class="hidden text-muted hover:text-ink sm:inline">Dashboard</a>
                        <svg class="hidden size-4 text-line sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="truncate font-medium text-ink">Detail pembayaran</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" class="relative rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800" aria-label="Lihat notifikasi">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="absolute right-1.5 top-1.5 size-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                        </button>
                        <span class="hidden h-6 w-px bg-line sm:block"></span>
                        <span class="hidden text-sm text-muted sm:inline">Kamis, 23 Juli 2026</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Manajemen pembayaran</span>
                                <span class="rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-900">{{ $invoice['status'] }}</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Detail {{ $invoice['number'] }}</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Pantau rincian tagihan, pembayaran masuk, dan sisa outstanding untuk invoice ini.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('api.invoices.pdf.download', ['invoice' => $invoiceModel]) }}" class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M7 3h7l4 4v14H7zM14 3v5h4M10 13h5M10 17h3" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Lihat invoice
                            </a>
                            <a
                                href="{{ $waLink ?? '#' }}"
                                @if ($waLink) target="_blank" rel="noopener" @else aria-disabled="true" @endif
                                class="inline-flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3.5 py-2 text-sm font-semibold text-green-800 hover:bg-green-100 {{ $waLink ? '' : 'pointer-events-none opacity-50' }}"
                            >
                                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12.04 3.5a8.45 8.45 0 0 0-7.3 12.7L3.75 20l3.9-1.02A8.44 8.44 0 1 0 12.04 3.5Zm0 1.45a6.99 6.99 0 0 1 5.92 10.72 6.99 6.99 0 0 1-9.98 1.86l-.28-.17-2.31.61.62-2.25-.18-.29a7 7 0 0 1 6.21-10.48Zm-2.2 3.48c-.15-.34-.31-.35-.46-.36h-.4c-.14 0-.36.05-.55.26-.19.21-.72.7-.72 1.7s.74 1.98.84 2.12c.1.14 1.43 2.29 3.55 3.12 1.76.7 2.12.56 2.5.52.38-.03 1.23-.5 1.4-.99.18-.48.18-.9.13-.99-.05-.09-.19-.14-.4-.24-.2-.1-1.23-.61-1.42-.68-.19-.07-.33-.1-.47.1-.14.21-.54.68-.66.82-.12.14-.24.16-.45.05-.2-.1-.87-.32-1.66-1.02-.61-.55-1.03-1.23-1.15-1.43-.12-.21-.01-.32.09-.42.09-.09.2-.24.31-.36.1-.12.14-.21.2-.35.07-.14.04-.26-.02-.36-.05-.1-.46-1.12-.64-1.52Z"/>
                                </svg>
                                Kirim via WA
                            </a>
                            <a href="#record-payment" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                                </svg>
                                Catat pembayaran
                            </a>
                        </div>
                    </div>

                    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                        <div class="min-w-0 space-y-6">
                            <section class="rounded-xl bg-white border border-line" aria-labelledby="invoice-info-heading">
                                <div class="border-b border-line px-5 py-4 sm:px-6">
                                    <h2 id="invoice-info-heading" class="font-semibold text-ink">Informasi invoice</h2>
                                    <p class="mt-1 text-sm text-muted">Detail pelanggan, tanggal, dan termin pembayaran.</p>
                                </div>
                                <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-2">
                                    <div>
                                        <p class="text-xs font-semibold text-muted">Ditagihkan kepada</p>
                                        <h3 class="mt-2 text-lg font-semibold text-ink">{{ $invoice['customer'] }}</h3>
                                        <p class="mt-2 text-sm leading-6 text-muted">{{ $invoice['address'] }}<br>{{ $invoice['email'] }}<br>{{ $invoice['phone'] }}</p>
                                    </div>
                                    <dl class="grid grid-cols-[1fr_auto] gap-x-6 gap-y-3 text-sm">
                                        <dt class="text-muted">Nomor invoice</dt>
                                        <dd class="font-mono font-semibold text-ink">{{ $invoice['number'] }}</dd>
                                        <dt class="text-muted">Tanggal invoice</dt>
                                        <dd class="font-medium text-ink">{{ $invoice['issued_at'] }}</dd>
                                        <dt class="text-muted">Jatuh tempo</dt>
                                        <dd class="font-semibold text-red-700">{{ $invoice['due_at'] }}</dd>
                                        <dt class="text-muted">Termin</dt>
                                        <dd class="font-medium text-ink">{{ $invoice['terms'] }}</dd>
                                    </dl>
                                </div>
                            </section>

                            <section class="rounded-xl bg-white border border-line" aria-labelledby="invoice-items-heading">
                                <div class="border-b border-line px-5 py-4 sm:px-6">
                                    <h2 id="invoice-items-heading" class="font-semibold text-ink">Rincian tagihan</h2>
                                    <p class="mt-1 text-sm text-muted">Item invoice dan total yang perlu diselesaikan.</p>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[620px] text-left text-sm">
                                        <thead>
                                            <tr class="border-b border-line text-xs font-semibold text-muted">
                                                <th class="px-5 py-3 sm:px-6">Item</th>
                                                <th class="px-5 py-3 text-center">Jumlah</th>
                                                <th class="px-5 py-3 text-right">Harga</th>
                                                <th class="px-5 py-3 text-right sm:px-6">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-line">
                                            @foreach ($items as $item)
                                                <tr>
                                                    <td class="px-5 py-4 sm:px-6">
                                                        <p class="font-medium text-ink">{{ $item['name'] }}</p>
                                                        <p class="mt-1 text-xs text-muted">{{ $item['spec'] }}</p>
                                                    </td>
                                                    <td class="px-5 py-4 text-center text-muted">{{ $item['quantity'] }}</td>
                                                    <td class="px-5 py-4 text-right text-muted">{{ $item['price'] }}</td>
                                                    <td class="px-5 py-4 text-right font-semibold text-ink sm:px-6">{{ $item['total'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <dl class="ml-auto w-full max-w-sm space-y-3 px-5 py-5 text-sm sm:px-6">
                                    <div class="flex justify-between gap-6">
                                        <dt class="text-muted">Subtotal</dt>
                                        <dd class="font-medium text-ink">{{ $invoice['subtotal'] }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-6">
                                        <dt class="text-muted">Diskon</dt>
                                        <dd class="font-medium text-red-700">{{ $invoice['discount'] }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-6">
                                        <dt class="text-muted">PPN 11%</dt>
                                        <dd class="font-medium text-ink">{{ $invoice['tax'] }}</dd>
                                    </div>
                                    <div class="flex items-end justify-between gap-6 border-t border-line pt-4">
                                        <dt class="font-semibold text-ink">Total invoice</dt>
                                        <dd class="text-lg font-bold tracking-[-0.025em] text-brand-800">{{ $invoice['total'] }}</dd>
                                    </div>
                                </dl>
                            </section>

                            <section
                                class="rounded-xl bg-white border border-line"
                                aria-labelledby="production-workflow-heading"
                                x-data="productionStatusForm"
                                data-current-status="{{ $invoice['production_status_key'] }}"
                                data-endpoint="{{ route('api.invoices.production-status.update', ['invoice' => $invoiceModel]) }}"
                                data-production-steps="{{ json_encode($productionSteps, JSON_THROW_ON_ERROR) }}"
                            >
                                <div class="border-b border-line px-5 py-4 sm:px-6">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h2 id="production-workflow-heading" class="font-semibold text-ink">Workflow produksi</h2>
                                            <p class="mt-1 text-sm text-muted">Pantau alur dari DP, ACC desain, sablon/cetak, sampai pengiriman.</p>
                                        </div>
                                        <span
                                            class="inline-flex w-fit rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800"
                                            x-text="currentLabel"
                                        >{{ $invoice['production_status'] }}</span>
                                    </div>
                                </div>
                                <div class="p-5 sm:p-6">
                                    <ol class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                                        @foreach ($productionSteps as $index => $step)
                                            <li
                                                class="rounded-lg border p-3 transition-colors duration-200"
                                                :class="stepCardClass(@js($step['key']))"
                                                :aria-current="currentStatus === @js($step['key']) ? 'step' : null"
                                            >
                                                <span
                                                    class="grid size-7 place-items-center rounded-full text-xs font-bold transition-colors duration-200"
                                                    :class="stepNumberClass(@js($step['key']))"
                                                    x-text="stepNumber(@js($step['key']), {{ $index + 1 }})"
                                                >
                                                    {{ $index < $currentProductionIndex ? '✓' : $index + 1 }}
                                                </span>
                                                <p class="mt-3 text-sm font-semibold">{{ $step['label'] }}</p>
                                            </li>
                                        @endforeach
                                    </ol>

                                    @if ($canUpdateProduction)
                                        <form class="mt-5 border-t border-line pt-5" @submit.prevent="submit()">
                                            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                                <div class="max-w-xl">
                                                    <label for="production-status" class="block text-sm font-semibold text-ink">Update status produksi</label>
                                                    <p class="mt-1 text-xs leading-5 text-muted">Status ini diubah manual sesuai progres pekerjaan. Status pembayaran tetap dihitung otomatis dari nominal yang tercatat.</p>
                                                </div>
                                                <div class="flex w-full flex-col gap-2 sm:flex-row lg:w-auto">
                                                    <select
                                                        id="production-status"
                                                        name="production_status"
                                                        data-testid="production-status-select"
                                                        class="form-control min-w-56"
                                                        x-model="selectedStatus"
                                                        :disabled="saving"
                                                        @change="clearNotice()"
                                                    >
                                                        @foreach ($productionSteps as $step)
                                                            <option
                                                                value="{{ $step['key'] }}"
                                                                @disabled(
                                                                    $step['key'] === \App\Models\Invoice::PRODUCTION_COMPLETED
                                                                    && $invoice['payment_status'] !== \App\Models\Invoice::PAYMENT_PAID
                                                                )
                                                            >{{ $step['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button
                                                        type="submit"
                                                        aria-label="Simpan status produksi"
                                                        data-testid="production-status-submit"
                                                        class="inline-flex min-w-32 items-center justify-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-60"
                                                        :disabled="saving || selectedStatus === currentStatus"
                                                    >
                                                        <svg x-show="saving" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                            <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke-linecap="round"/>
                                                        </svg>
                                                        <span x-text="saving ? 'Menyimpan...' : 'Simpan status'">Simpan status</span>
                                                    </button>
                                                </div>
                                            </div>

                                            <p
                                                x-cloak
                                                x-show="message"
                                                x-text="message"
                                                data-testid="production-status-notice"
                                                class="mt-3 rounded-lg border px-3 py-2 text-sm"
                                                :class="messageType === 'success' ? 'border-green-200 bg-green-50 text-green-900' : 'border-red-200 bg-red-50 text-red-900'"
                                                role="status"
                                            ></p>

                                            @if ($invoice['payment_status'] !== \App\Models\Invoice::PAYMENT_PAID)
                                                <p class="mt-2 text-xs text-muted">Tahap “Lunas & Selesai” aktif setelah sisa tagihan menjadi Rp0.</p>
                                            @endif
                                        </form>
                                    @endif

                                    <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                                        <div class="rounded-lg border border-line bg-canvas p-4">
                                            <p class="text-xs font-semibold text-muted">Catatan desain / posisi logo</p>
                                            <p class="mt-2 text-sm leading-6 text-ink">{{ $invoice['design_notes'] }}</p>
                                        </div>
                                        @if ($invoice['mockup_url'])
                                            <a href="{{ $invoice['mockup_url'] }}" target="_blank" rel="noopener" class="rounded-lg border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900 hover:bg-brand-100">
                                                <span class="text-xs font-semibold text-brand-800">Mockup/attachment</span>
                                                <span class="mt-2 block font-semibold">Buka file mockup</span>
                                                <span class="mt-1 block break-all text-xs text-brand-800">{{ $invoice['mockup_url'] }}</span>
                                            </a>
                                        @else
                                            <div class="rounded-lg border border-line bg-canvas p-4 text-sm text-muted">
                                                <span class="text-xs font-semibold">Mockup/attachment</span>
                                                <span class="mt-2 block">Belum ada file mockup.</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </section>

                            <section
                                id="record-payment"
                                class="rounded-xl bg-white border border-line"
                                aria-labelledby="record-payment-heading"
                                x-data="recordPaymentForm({ remainingAmount: @js($invoice['remaining_amount']) })"
                            >
                                <div class="border-b border-line px-5 py-4 sm:px-6">
                                    <h2 id="record-payment-heading" class="font-semibold text-ink">Catat pembayaran</h2>
                                    <p class="mt-1 text-sm text-muted">Catat pembayaran masuk untuk invoice ini.</p>
                                </div>

                                <form class="space-y-5 p-5 sm:p-6" action="#" method="post" novalidate @submit.prevent="submit()">
                                    <div
                                        x-cloak
                                        x-show="validationMessages.length > 0"
                                        class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900"
                                        role="alert"
                                        data-testid="payment-validation-summary"
                                    >
                                        <p class="font-semibold">Periksa data pembayaran</p>
                                        <ul class="mt-2 list-disc space-y-1 pl-5">
                                            <template x-for="message in validationMessages" :key="message">
                                                <li x-text="message"></li>
                                            </template>
                                        </ul>
                                    </div>

                                    <div
                                        x-cloak
                                        x-show="savedPayment"
                                        x-transition.opacity
                                        class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900"
                                        role="status"
                                        data-testid="payment-saved-notice"
                                    >
                                        <p class="font-semibold">Pembayaran tercatat</p>
                                        <p class="mt-1 leading-5" x-text="savedPayment ? `${savedPayment.amount} via ${savedPayment.method} dengan referensi ${savedPayment.reference}.` : ''"></p>
                                    </div>

                                    <div class="grid gap-5 md:grid-cols-2">
                                        <div>
                                            <div class="mb-2 flex items-center justify-between gap-3">
                                                <label for="payment-amount" class="block text-sm font-medium text-ink">Nominal pembayaran</label>
                                                <button type="button" class="text-xs font-semibold text-brand-800 hover:text-brand-900" @click="useRemainingAmount()">Gunakan sisa</button>
                                            </div>
                                            <input
                                                id="payment-amount"
                                                name="amount"
                                                type="number"
                                                min="1"
                                                step="1000"
                                                class="form-control"
                                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors.amount }"
                                                x-model.number="form.amount"
                                                @input="clearFieldError('amount')"
                                                aria-describedby="payment-amount-help payment-amount-error"
                                            >
                                            <p id="payment-amount-help" class="mt-1.5 text-xs text-muted">Sisa tagihan: {{ $invoice['remaining'] }}. Nilai saat ini: <span x-text="formattedAmount"></span>.</p>
                                            <p id="payment-amount-error" x-show="fieldErrors.amount" x-text="fieldErrors.amount" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                        </div>

                                        <div>
                                            <label for="payment-date" class="mb-2 block text-sm font-medium text-ink">Tanggal pembayaran</label>
                                            <input
                                                id="payment-date"
                                                name="paid_at"
                                                type="date"
                                                class="form-control"
                                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors.paidAt }"
                                                x-model="form.paidAt"
                                                @input="clearFieldError('paidAt')"
                                                aria-describedby="payment-date-error"
                                            >
                                            <p id="payment-date-error" x-show="fieldErrors.paidAt" x-text="fieldErrors.paidAt" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                        </div>

                                        <div>
                                            <label for="payment-method" class="mb-2 block text-sm font-medium text-ink">Metode pembayaran</label>
                                            <select
                                                id="payment-method"
                                                name="method"
                                                class="form-control"
                                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors.method }"
                                                x-model="form.method"
                                                @change="clearFieldError('method')"
                                                aria-describedby="payment-method-error"
                                            >
                                                <option>Transfer BCA</option>
                                                <option>Transfer Mandiri</option>
                                                <option>Kartu kredit</option>
                                                <option>Tunai</option>
                                            </select>
                                            <p id="payment-method-error" x-show="fieldErrors.method" x-text="fieldErrors.method" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                        </div>

                                        <div>
                                            <label for="payment-reference" class="mb-2 block text-sm font-medium text-ink">Nomor referensi</label>
                                            <input
                                                id="payment-reference"
                                                name="reference"
                                                type="text"
                                                class="form-control"
                                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors.reference }"
                                                x-model="form.reference"
                                                @input="clearFieldError('reference')"
                                                aria-describedby="payment-reference-error"
                                            >
                                            <p id="payment-reference-error" x-show="fieldErrors.reference" x-text="fieldErrors.reference" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="payment-notes" class="mb-2 block text-sm font-medium text-ink">Catatan internal</label>
                                        <textarea id="payment-notes" name="notes" rows="3" class="form-control min-h-24" x-model="form.notes"></textarea>
                                    </div>

                                    <div class="flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-xs leading-5 text-muted">Form ini belum menyimpan ke backend. Data dipakai untuk verifikasi alur frontend pembayaran.</p>
                                        <button
                                            type="submit"
                                            class="inline-flex min-w-40 items-center justify-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-wait disabled:opacity-70"
                                            :disabled="saving"
                                        >
                                            <svg x-show="! saving" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <svg x-show="saving" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke-linecap="round"/>
                                            </svg>
                                            <span x-text="saving ? 'Mencatat...' : 'Simpan pembayaran'"></span>
                                        </button>
                                    </div>
                                </form>
                            </section>
                        </div>

                        <aside class="space-y-6 xl:sticky xl:top-22" aria-label="Section pembayaran">
                            <section class="rounded-xl bg-white p-5 border border-line sm:p-6" aria-labelledby="payment-summary-heading">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 id="payment-summary-heading" class="font-semibold text-ink">Pembayaran</h2>
                                        <p class="mt-1 text-sm text-muted">Status pelunasan invoice ini.</p>
                                    </div>
                                    <span class="rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-900">Outstanding</span>
                                </div>

                                <div class="mt-6">
                                    <div class="flex items-end justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-semibold text-muted">Sudah dibayar</p>
                                            <p class="mt-1 text-2xl font-semibold tracking-[-0.025em] text-ink">{{ $invoice['paid'] }}</p>
                                        </div>
                                        <p class="text-sm font-semibold text-brand-800">{{ $invoice['progress'] }}%</p>
                                    </div>
                                    <div class="mt-4 h-3 rounded-full bg-canvas">
                                        <div class="h-3 rounded-full bg-brand-600" style="width: {{ $invoice['progress'] }}%"></div>
                                    </div>
                                    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4">
                                        <p class="text-xs font-semibold text-red-800">Sisa tagihan</p>
                                        <p class="mt-1 text-xl font-bold tracking-[-0.025em] text-red-900">{{ $invoice['remaining'] }}</p>
                                        <p class="mt-2 text-xs leading-5 text-red-800">Jatuh tempo {{ $invoice['due_at'] }}. Kirim pengingat bila belum ada pembayaran lanjutan.</p>
                                    </div>
                                    <div class="mt-3 rounded-lg border border-brand-200 bg-brand-50 p-4">
                                        <p class="text-xs font-semibold text-brand-800">Minimal DP sebelum produksi</p>
                                        <p class="mt-1 text-xl font-bold tracking-[-0.025em] text-brand-900">{{ $invoice['dp_required'] }}</p>
                                        <p class="mt-2 text-xs leading-5 text-brand-800">Jika DP sudah aman dan mockup ACC, status bisa naik ke proses sablon/cetak.</p>
                                    </div>
                                </div>

                                <div class="mt-5 grid gap-2">
                                    <a href="#record-payment" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                                        </svg>
                                        Catat pembayaran
                                    </a>
                                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg border border-brand-300 bg-white px-3.5 py-2 text-sm font-semibold text-brand-800 hover:bg-brand-50">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M4 5h16v14H4zM4 7l8 6 8-6" stroke-linejoin="round"/>
                                        </svg>
                                        Kirim pengingat
                                    </button>
                                </div>
                            </section>

                            <section class="rounded-xl bg-white border border-line" aria-labelledby="payment-history-heading">
                                <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                                    <div>
                                        <h2 id="payment-history-heading" class="font-semibold text-ink">Riwayat pembayaran</h2>
                                        <p class="mt-1 text-sm text-muted">Pembayaran yang sudah dicatat.</p>
                                    </div>
                                    <a href="{{ route('payments.history.index') }}" class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-brand-800 hover:bg-brand-50">Lihat semua</a>
                                </div>
                                <ol class="divide-y divide-line">
                                    @foreach ($payments as $payment)
                                        <li class="px-5 py-4">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <p class="text-sm font-semibold text-ink">{{ $payment['method'] }}</p>
                                                    <p class="mt-1 text-xs text-muted">{{ $payment['date'] }} - {{ $payment['reference'] }}</p>
                                                </div>
                                                <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">{{ $payment['status'] }}</span>
                                            </div>
                                            <p class="mt-3 text-sm font-semibold text-brand-800">{{ $payment['amount'] }}</p>
                                        </li>
                                    @endforeach
                                </ol>
                            </section>

                            <section class="rounded-xl border border-brand-200 bg-brand-50 p-5 text-sm text-brand-900" aria-labelledby="payment-method-heading">
                                <h2 id="payment-method-heading" class="font-semibold">Instruksi pembayaran</h2>
                                <dl class="mt-4 space-y-3">
                                    @foreach ($paymentMethods as $method)
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-brand-800">{{ $method['label'] }}</dt>
                                            <dd class="text-right font-semibold">{{ $method['value'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </section>
                        </aside>
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
