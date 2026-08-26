@php
    $formatRupiah = static fn (float $amount): string => 'Rp'.number_format($amount, 0, ',', '.');
    $formatDate = static fn ($date): string => $date?->locale('id')->translatedFormat('j F Y') ?? '-';
    $paidAmount = $invoiceModel->verifiedPaidAmount();
    $remainingAmount = $invoiceModel->remainingAmount();
    $totalAmount = (float) $invoiceModel->total_amount;
    $isPaid = $remainingAmount <= 0;
    $paymentProgress = $totalAmount > 0
        ? ($isPaid ? 100 : min(100, round(($paidAmount / $totalAmount) * 100)))
        : 0;
    $isOverdue = ! $isPaid && $invoiceModel->due_date?->isPast();
    // Rincian ongkir (Phase 5) - purely a display breakdown from the
    // existing shipping_type/shipping_cost/is_free_shipping fields.
    // Never affects $totalAmount above: CalculateInvoiceTotals already
    // only adds shipping_cost into total_amount for
    // SHIPPING_PAID_BY_CUSTOMER, and leaves company-paid free shipping out
    // of it entirely (absorbed via gross_profit instead) - this section
    // just makes that existing rule visible, it doesn't recompute anything.
    $hasShippingLine = $invoiceModel->shipping_type !== \App\Models\Invoice::SHIPPING_NONE;
    $shippingLabel = match ($invoiceModel->shipping_type) {
        \App\Models\Invoice::SHIPPING_PAID_BY_CUSTOMER => $formatRupiah((float) $invoiceModel->shipping_cost),
        \App\Models\Invoice::SHIPPING_COMPANY_FREE_SHIPPING => 'Gratis (ditanggung perusahaan)',
        default => null,
    };
    $effectivePaymentStatus = $isPaid
        ? \App\Models\Invoice::PAYMENT_PAID
        : ($isOverdue ? \App\Models\Invoice::PAYMENT_OVERDUE : $invoiceModel->payment_status);
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
        'is_paid' => $isPaid,
        'production_status' => $invoiceModel->productionStatusLabel(),
        'production_status_key' => $invoiceModel->production_status,
        'dp_required' => $formatRupiah($invoiceModel->requiredDpAmount()),
        'design_notes' => $invoiceModel->design_notes ?: 'Belum ada catatan desain.',
        'mockup_url' => $invoiceModel->mockup_url,
        'is_cancelled' => $invoiceModel->status === \App\Models\Invoice::STATUS_CANCELLED,
        'is_draft' => $invoiceModel->status === \App\Models\Invoice::STATUS_DRAFT,
        'is_editable' => $invoiceModel->isEditable(),
        'can_be_cancelled' => $invoiceModel->canBeCancelled(),
        'cancellation_reason' => $invoiceModel->cancellation_reason,
        'cancelled_at' => $formatDate($invoiceModel->cancelled_at),
        'has_shipping_line' => $hasShippingLine,
        'shipping_label' => $shippingLabel,
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
            'quantity' => trim("{$quantity} Pcs"),
            'price' => $formatRupiah((float) $item->unit_price),
            'total' => $formatRupiah((float) $item->total_amount),
        ];
    });

    $productionSteps = \App\Models\Invoice::productionWorkflow();
    $currentProductionIndex = collect($productionSteps)
        ->search(fn (array $step): bool => $step['key'] === $invoice['production_status_key']);

    $deliveryNoteAvailable = $invoiceModel->canGenerateDeliveryNote();

    $payments = $invoiceModel->payments->map(static fn ($payment): array => [
        'date' => $formatDate($payment->payment_date),
        'method' => $payment->methodLabel(),
        'reference' => $payment->reference ?: '-',
        'amount' => $formatRupiah((float) $payment->amount),
        'status' => $payment->statusLabel(),
    ]);

    $paymentMethods = [
        ['label' => 'Bank', 'value' => 'Bank Central Asia'],
        ['label' => 'No. rekening', 'value' => '012 345 6789'],
        ['label' => 'Atas nama', 'value' => 'PT Ruang Karya Digital'],
    ];
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
                        class="btn-icon mr-3 border-transparent lg:hidden"
                        @click="sidebarOpen = true"
                        aria-controls="app-sidebar"
                        :aria-expanded="sidebarOpen"
                        aria-label="Buka navigasi"
                    >
                        <i class="iconify tabler--align-left text-xl"></i>
                    </button>
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <a href="{{ route('dashboard') }}" class="hidden text-muted hover:text-ink sm:inline">Dashboard</a>
                        <i class="iconify tabler--chevron-right hidden text-sm text-line sm:block"></i>
                        <span class="truncate font-medium text-ink">Detail pembayaran</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <x-notification-bell />
                        <span class="hidden h-6 w-px bg-line sm:block"></span>
                        <span class="hidden text-sm text-muted sm:inline">{{ now(config('app.timezone'))->locale('id')->translatedFormat('l, j F Y') }}</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="badge badge-brand">Manajemen pembayaran</span>
                                <span @class([
                                    'badge',
                                    'badge-success' => $invoice['is_paid'],
                                    'badge-warning' => ! $invoice['is_paid'],
                                ])>{{ $invoice['status'] }}</span>
                                @if ($invoice['is_cancelled'])
                                    <span class="badge badge-danger">Order dibatalkan</span>
                                @endif
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Detail {{ $invoice['number'] }}</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">
                                @if ($invoice['is_cancelled'])
                                    Order ini dibatalkan pada {{ $invoice['cancelled_at'] }}.
                                    @if ($invoice['cancellation_reason'])
                                        Alasan: {{ $invoice['cancellation_reason'] }}.
                                    @endif
                                @else
                                    {{ $invoice['is_paid'] ? 'Invoice telah lunas dan tidak memiliki sisa tagihan.' : 'Pantau rincian tagihan, pembayaran masuk, dan sisa outstanding untuk invoice ini.' }}
                                @endif
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-2"
                            x-data="{ deliveryNoteAvailable: @js($deliveryNoteAvailable) }"
                            @invoice-production-status-updated.window="deliveryNoteAvailable = ['ready_for_pickup', 'completed'].includes($event.detail)"
                        >
                            <a href="{{ route('api.invoices.pdf.download', ['invoice' => $invoiceModel]) }}" class="btn btn-outline">
                                <i class="iconify tabler--file-text text-base"></i>
                                Lihat invoice
                            </a>
                            <a
                                href="{{ route('api.invoices.delivery-note.pdf.download', ['invoice' => $invoiceModel]) }}"
                                x-cloak
                                x-show="deliveryNoteAvailable"
                                class="btn btn-outline"
                            >
                                    <i class="iconify tabler--truck-delivery text-base"></i>
                                    Surat jalan
                            </a>
                            @if ($invoice['is_editable'] && $canUpdateProduction)
                                <a
                                    href="{{ route('invoices.edit', $invoiceModel) }}"
                                    class="btn btn-outline"
                                >
                                    <i class="iconify tabler--edit text-base"></i>
                                    Edit invoice
                                </a>
                            @endif
                            @unless ($invoice['is_paid'] || $invoice['is_cancelled'])
                                <div
                                    class="relative"
                                    x-data="invoiceWhatsAppDelivery"
                                    data-endpoint="{{ route('api.invoices.send-whatsapp', ['invoice' => $invoiceModel]) }}"
                                    data-sent="{{ $invoiceModel->status === \App\Models\Invoice::STATUS_SENT ? 'true' : 'false' }}"
                                    data-purpose="invoice"
                                >
                                    <button
                                        type="button"
                                        class="btn border-green-200 bg-green-50 text-green-800 hover:bg-green-100 disabled:cursor-wait"
                                        :disabled="sending"
                                        :aria-busy="sending"
                                        @click="send()"
                                    >
                                        <i class="iconify tabler--brand-whatsapp text-base"></i>
                                        <span x-text="sending ? 'Membuka WA…' : (sent ? 'Ingatkan via WA' : 'Kirim via WA')">Kirim via WA</span>
                                    </button>
                                    <p x-cloak x-show="this.message" x-text="this.message" class="absolute right-0 top-full z-10 mt-2 w-64 rounded-lg border px-3 py-2 text-xs shadow-sm" :class="(this.messageType ?? 'error') === 'success' ? 'border-green-200 bg-green-50 text-green-900' : 'border-red-200 bg-red-50 text-red-900'" role="status"></p>
                                </div>
                                <a href="#record-payment" class="btn btn-primary">
                                    <i class="iconify tabler--plus text-base"></i>
                                    Catat pembayaran
                                </a>
                            @endunless
                            @if ($invoice['can_be_cancelled'])
                                <div class="relative" x-data="cancelOrderAction" data-endpoint="{{ route('api.invoices.cancel.store', ['invoice' => $invoiceModel]) }}">
                                    <button
                                        type="button"
                                        class="btn btn-danger-outline disabled:cursor-wait"
                                        :disabled="cancelling"
                                        :aria-busy="cancelling"
                                        @click="cancel()"
                                    >
                                        <i class="iconify tabler--x text-base"></i>
                                        <span x-text="cancelling ? 'Membatalkan...' : 'Batalkan order'"></span>
                                    </button>
                                    <p x-cloak x-show="this.message" x-text="this.message" class="absolute right-0 top-full z-10 mt-2 w-64 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-900 shadow-sm" role="alert"></p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                        <div class="min-w-0 space-y-6">
                            <section class="card" aria-labelledby="invoice-info-heading">
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
                                        <dd @class([
                                            'font-semibold',
                                            'text-ink' => $invoice['is_paid'],
                                            'text-red-700' => ! $invoice['is_paid'],
                                        ])>{{ $invoice['due_at'] }}</dd>
                                        <dt class="text-muted">Termin</dt>
                                        <dd class="font-medium text-ink">{{ $invoice['terms'] }}</dd>
                                    </dl>
                                </div>
                            </section>

                            <section class="card" aria-labelledby="invoice-items-heading">
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
                                    @if ($invoice['has_shipping_line'])
                                        <div class="flex justify-between gap-6">
                                            <dt class="text-muted">Ongkir</dt>
                                            <dd class="font-medium text-ink">{{ $invoice['shipping_label'] }}</dd>
                                        </div>
                                    @endif
                                    <div class="flex items-end justify-between gap-6 border-t border-line pt-4">
                                        <dt class="font-semibold text-ink">Total invoice</dt>
                                        <dd class="text-lg font-bold tracking-[-0.025em] text-brand-800">{{ $invoice['total'] }}</dd>
                                    </div>
                                </dl>
                            </section>

                            <section
                                class="card"
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
                                            class="badge badge-brand w-fit"
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

                                    @if ($canUpdateProduction && ! $invoice['is_cancelled'])
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
                                                        class="btn btn-primary min-w-32 disabled:cursor-not-allowed"
                                                        :disabled="saving || selectedStatus === currentStatus"
                                                    >
                                                        <i x-show="saving" class="iconify tabler--loader-2 animate-spin text-base"></i>
                                                        <span x-text="saving ? 'Menyimpan...' : 'Simpan status'">Simpan status</span>
                                                    </button>
                                                </div>
                                            </div>

                                            <p
                                                x-cloak
                                                x-show="this.message"
                                                x-text="this.message"
                                                data-testid="production-status-notice"
                                                class="mt-3 rounded-lg border px-3 py-2 text-sm"
                                                :class="(this.messageType ?? 'error') === 'success' ? 'border-green-200 bg-green-50 text-green-900' : 'border-red-200 bg-red-50 text-red-900'"
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

                            @if ($invoice['is_cancelled'])
                                <section
                                    id="record-payment"
                                    class="rounded-xl border border-red-200 bg-red-50 p-5 sm:p-6"
                                    aria-labelledby="record-payment-heading"
                                    data-testid="order-cancelled-state"
                                >
                                    <div class="flex items-start gap-4">
                                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-red-600 text-white" aria-hidden="true">
                                            <i class="iconify tabler--x text-lg"></i>
                                        </span>
                                        <div>
                                            <h2 id="record-payment-heading" class="font-semibold text-red-950">Order dibatalkan</h2>
                                            <p class="mt-1 text-sm leading-6 text-red-800">Order ini sudah ditutup. Pembayaran tidak dapat dicatat.</p>
                                        </div>
                                    </div>
                                </section>
                            @elseif ($invoice['is_paid'])
                                <section
                                    id="record-payment"
                                    class="rounded-xl border border-green-200 bg-green-50 p-5 sm:p-6"
                                    aria-labelledby="record-payment-heading"
                                    data-testid="payment-complete-state"
                                >
                                    <div class="flex items-start gap-4">
                                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-green-600 text-white" aria-hidden="true">
                                            <i class="iconify tabler--check text-lg"></i>
                                        </span>
                                        <div>
                                            <h2 id="record-payment-heading" class="font-semibold text-green-950">Invoice sudah lunas</h2>
                                            <p class="mt-1 text-sm leading-6 text-green-800">Sisa tagihan Rp0. Pembayaran tambahan tidak dapat dicatat.</p>
                                        </div>
                                    </div>
                                </section>
                            @else
                                <section
                                    id="record-payment"
                                    class="card"
                                    aria-labelledby="record-payment-heading"
                                    x-data="recordPaymentForm({
                                        remainingAmount: @js($invoice['remaining_amount']),
                                        endpoint: @js(route('api.invoices.payments.store', $invoiceModel)),
                                    })"
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
                                                <label for="payment-amount" class="block text-sm font-medium text-ink">Nominal pembayaran / DP</label>
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
                                            <p id="payment-amount-help" class="mt-1.5 text-xs text-muted">DP dicatat sebagai payment record. Sisa tagihan: {{ $invoice['remaining'] }}. Nilai saat ini: <span x-text="formattedAmount"></span>.</p>
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
                                                <option value="transfer_bca">Transfer BCA</option>
                                                <option value="transfer_mandiri">Transfer Mandiri</option>
                                                <option value="credit_card">Kartu kredit</option>
                                                <option value="cash">Tunai</option>
                                            </select>
                                            <p id="payment-method-error" x-show="fieldErrors.method" x-text="fieldErrors.method" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                        </div>

                                        <div>
                                            <label for="payment-reference" class="mb-2 block text-sm font-medium text-ink">Nomor referensi <span class="font-normal text-muted">(opsional)</span></label>
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
                                        </div>
                                    </div>

                                    <div>
                                        <label for="payment-notes" class="mb-2 block text-sm font-medium text-ink">Catatan internal</label>
                                        <textarea id="payment-notes" name="notes" rows="3" class="form-control min-h-24" x-model="form.notes"></textarea>
                                    </div>

                                    <div class="flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-xs leading-5 text-muted">Pembayaran tersimpan ke invoice dan langsung memperbarui status piutang.</p>
                                        <button
                                            type="submit"
                                            class="btn btn-primary min-w-40 disabled:cursor-wait"
                                            :disabled="saving"
                                        >
                                            <i x-show="! saving" class="iconify tabler--check text-base"></i>
                                            <i x-show="saving" class="iconify tabler--loader-2 animate-spin text-base"></i>
                                            <span x-text="saving ? 'Mencatat...' : 'Simpan pembayaran'"></span>
                                        </button>
                                    </div>
                                </form>
                                </section>
                            @endif
                        </div>

                        <aside class="space-y-6 xl:sticky xl:top-22" aria-label="Section pembayaran">
                            <section class="card p-5 sm:p-6" aria-labelledby="payment-summary-heading">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 id="payment-summary-heading" class="font-semibold text-ink">Pembayaran</h2>
                                        <p class="mt-1 text-sm text-muted">Status pelunasan invoice ini.</p>
                                    </div>
                                    <span @class([
                                        'badge',
                                        'badge-success' => $invoice['is_paid'],
                                        'badge-warning' => ! $invoice['is_paid'],
                                    ])>{{ $invoice['is_paid'] ? 'Lunas' : 'Outstanding' }}</span>
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
                                    @if ($invoice['is_paid'])
                                        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4">
                                            <p class="text-xs font-semibold text-green-800">Tagihan lunas</p>
                                            <p class="mt-1 text-xl font-bold tracking-[-0.025em] text-green-900">{{ $invoice['remaining'] }}</p>
                                            <p class="mt-2 text-xs leading-5 text-green-800">Seluruh pembayaran untuk invoice ini telah diterima.</p>
                                        </div>
                                    @else
                                        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4">
                                            <p class="text-xs font-semibold text-red-800">Sisa tagihan</p>
                                            <p class="mt-1 text-xl font-bold tracking-[-0.025em] text-red-900">{{ $invoice['remaining'] }}</p>
                                            <p class="mt-2 text-xs leading-5 text-red-800">Jatuh tempo {{ $invoice['due_at'] }}. Kirim pengingat bila belum ada pembayaran lanjutan.</p>
                                        </div>
                                    @endif
                                    <div class="mt-3 rounded-lg border border-brand-200 bg-brand-50 p-4">
                                        <p class="text-xs font-semibold text-brand-800">Minimal DP sebelum produksi</p>
                                        <p class="mt-1 text-xl font-bold tracking-[-0.025em] text-brand-900">{{ $invoice['dp_required'] }}</p>
                                        <p class="mt-2 text-xs leading-5 text-brand-800">Jika DP sudah aman dan mockup ACC, status bisa naik ke proses sablon/cetak.</p>
                                    </div>
                                </div>

                                @unless ($invoice['is_paid'])
                                    <div class="mt-5 grid gap-2">
                                        <a href="#record-payment" class="btn btn-primary">
                                            <i class="iconify tabler--plus text-base"></i>
                                            Catat pembayaran
                                        </a>
                                        <div
                                            x-data="invoiceWhatsAppDelivery"
                                            data-endpoint="{{ route('api.invoices.send-whatsapp', ['invoice' => $invoiceModel]) }}"
                                            data-sent="{{ $invoiceModel->status === \App\Models\Invoice::STATUS_SENT ? 'true' : 'false' }}"
                                            data-purpose="reminder"
                                        >
                                            <button type="button" class="btn border-green-200 bg-green-50 text-green-800 hover:bg-green-100 disabled:cursor-wait" :disabled="sending" :aria-busy="sending" @click="send()">
                                                <i class="iconify tabler--brand-whatsapp text-base"></i>
                                                <span x-text="sending ? 'Membuka WA…' : 'Kirim pengingat WA'">Kirim pengingat WA</span>
                                            </button>
                                            <p x-cloak x-show="this.message" x-text="this.message" class="mt-2 max-w-sm rounded-lg border px-3 py-2 text-xs" :class="(this.messageType ?? 'error') === 'success' ? 'border-green-200 bg-green-50 text-green-900' : 'border-red-200 bg-red-50 text-red-900'" role="status"></p>
                                        </div>
                                    </div>
                                @endunless
                            </section>

                            <section class="card" aria-labelledby="payment-history-heading">
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
                                                <span class="badge badge-success">{{ $payment['status'] }}</span>
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
