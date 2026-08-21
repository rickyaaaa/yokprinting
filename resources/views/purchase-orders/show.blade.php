@php
    $currentUser = auth()->user();
    $role = $currentUser?->role === \App\Models\User::ROLE_OWNER
        ? null
        : $currentUser?->roleDefinition()->with('permissions')->first();
    $rolePermissions = $role && $role->status !== \App\Models\Role::STATUS_DISABLED
        ? $role->permissions->pluck('code')
        : collect();
    $can = fn (string $permission): bool => (bool) ($currentUser?->isActive() && (
        $currentUser->role === \App\Models\User::ROLE_OWNER || $rolePermissions->contains($permission)
    ));
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Detail Purchase Order YokPrinting.ID">
        <title>Detail Purchase Order - YokPrinting.ID</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"></div>
            <x-app-sidebar />

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button type="button" class="mr-3 rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800 lg:hidden" @click="sidebarOpen = true" aria-label="Buka navigasi">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/></svg>
                    </button>
                    <div class="flex items-center gap-2 text-sm">
                        <a href="{{ route('purchase-orders.index') }}" class="hidden text-muted hover:text-ink sm:inline">Purchase Order</a>
                        <span class="hidden text-line sm:inline">/</span>
                        <span class="font-medium text-ink">Detail</span>
                    </div>
                </header>

                <main
                    class="mx-auto w-full max-w-[1180px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                    x-data='purchaseOrderShowPage(@json(["purchaseOrderId" => $purchaseOrderId]))'
                    x-init="init()"
                >
                    <div x-show="error" x-cloak class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" x-text="error" role="alert"></div>
                    <div x-show="loading" class="rounded-xl border border-line bg-white p-10 text-center text-sm text-muted">Memuat detail PO...</div>

                    <template x-if="!loading && po">
                        <div class="space-y-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Pembelian</span>
                                    <h1 class="mt-3 font-mono text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]" x-text="po.po_number"></h1>
                                    <span class="mt-2 inline-block rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(po.status)" x-text="po.status_label"></span>
                                </div>
                                <a href="{{ route('purchase-orders.index') }}" class="inline-flex w-fit items-center gap-2 rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Kembali ke daftar
                                </a>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <button
                                    type="button"
                                    x-show="po.status === 'draft' && {{ $can('purchase_order.submit') ? 'true' : 'false' }}"
                                    class="rounded-lg border border-line px-4 py-2.5 text-sm font-semibold text-ink hover:bg-canvas disabled:cursor-wait disabled:opacity-60"
                                    :disabled="acting"
                                    @click="submitForApproval()"
                                >Ajukan untuk approval</button>
                                <button
                                    type="button"
                                    x-show="po.status === 'waiting_approval' && {{ $can('purchase_order.approve') ? 'true' : 'false' }}"
                                    class="rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-800 hover:bg-green-100 disabled:cursor-wait disabled:opacity-60"
                                    :disabled="acting"
                                    @click="approve()"
                                >Setujui PO</button>
                                <a
                                    x-show="['approved', 'partially_received'].includes(po.status) && {{ $can('goods_receipt.create') ? 'true' : 'false' }}"
                                    :href="`{{ route('goods-receipts.create') }}?purchase_order_id=${po.id}`"
                                    class="inline-flex items-center justify-center rounded-lg border border-line px-4 py-2.5 text-sm font-semibold text-ink hover:bg-canvas"
                                >Terima Barang</a>
                                <button
                                    type="button"
                                    x-show="po.can_be_cancelled && {{ $can('purchase_order.cancel') ? 'true' : 'false' }}"
                                    class="rounded-lg border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:cursor-wait disabled:opacity-60"
                                    :disabled="acting"
                                    @click="cancel()"
                                >Batalkan PO</button>
                            </div>

                            <section class="rounded-xl border border-line bg-white p-5 sm:p-6">
                                <h2 class="font-semibold text-ink">Informasi PO</h2>
                                <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div><dt class="text-xs font-semibold text-muted">Supplier</dt><dd class="mt-1 text-sm text-ink" x-text="po.supplier ? `${po.supplier.code} - ${po.supplier.name}` : '-'"></dd></div>
                                    <div><dt class="text-xs font-semibold text-muted">Tanggal PO</dt><dd class="mt-1 text-sm text-ink" x-text="formatDate(po.order_date)"></dd></div>
                                    <div><dt class="text-xs font-semibold text-muted">Perkiraan tiba</dt><dd class="mt-1 text-sm text-ink" x-text="po.expected_date ? formatDate(po.expected_date) : '-'"></dd></div>
                                    <div><dt class="text-xs font-semibold text-muted">Dibuat oleh</dt><dd class="mt-1 text-sm text-ink" x-text="po.created_by ?? '-'"></dd></div>
                                    <div x-show="po.approved_by"><dt class="text-xs font-semibold text-muted">Disetujui oleh</dt><dd class="mt-1 text-sm text-ink" x-text="`${po.approved_by} - ${formatDate(po.approved_at)}`"></dd></div>
                                    <div x-show="po.cancelled_by"><dt class="text-xs font-semibold text-muted">Dibatalkan oleh</dt><dd class="mt-1 text-sm text-ink" x-text="`${po.cancelled_by} - ${formatDate(po.cancelled_at)}`"></dd></div>
                                </dl>
                                <div class="mt-4" x-show="po.cancellation_reason">
                                    <dt class="text-xs font-semibold text-muted">Alasan pembatalan</dt>
                                    <dd class="mt-1 text-sm text-ink" x-text="po.cancellation_reason"></dd>
                                </div>
                                <div class="mt-4" x-show="po.notes">
                                    <dt class="text-xs font-semibold text-muted">Catatan</dt>
                                    <dd class="mt-1 whitespace-pre-line text-sm text-ink" x-text="po.notes"></dd>
                                </div>
                            </section>

                            <section class="rounded-xl border border-line bg-white">
                                <div class="border-b border-line px-5 py-4 sm:px-6">
                                    <h2 class="font-semibold text-ink">Daftar barang</h2>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[720px] text-left text-sm">
                                        <thead>
                                            <tr class="border-b border-line text-xs font-semibold text-muted">
                                                <th class="px-5 py-3 sm:px-6">Barang</th>
                                                <th class="px-4 py-3 text-right">Qty</th>
                                                <th class="px-4 py-3 text-right">Diterima</th>
                                                <th class="px-4 py-3 text-right">Harga satuan</th>
                                                <th class="px-4 py-3 text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-line">
                                            <template x-for="item in po.items" :key="item.id">
                                                <tr>
                                                    <td class="px-5 py-3 sm:px-6">
                                                        <span class="block font-medium text-ink" x-text="item.product_name"></span>
                                                        <span class="block font-mono text-xs text-muted" x-text="item.sku"></span>
                                                    </td>
                                                    <td class="px-4 py-3 text-right text-ink" x-text="item.quantity"></td>
                                                    <td class="px-4 py-3 text-right text-muted" x-text="item.received_quantity"></td>
                                                    <td class="px-4 py-3 text-right text-ink" x-text="formatRupiah(item.unit_price)"></td>
                                                    <td class="px-4 py-3 text-right font-semibold text-ink" x-text="formatRupiah(item.subtotal)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="space-y-2 border-t border-line px-5 py-4 text-sm sm:px-6">
                                    <div class="flex items-center justify-between gap-4 text-muted"><span>Subtotal barang</span><span class="font-medium text-ink" x-text="formatRupiah(po.subtotal)"></span></div>
                                    <div class="flex items-center justify-between gap-4 text-muted"><span>Ongkos kirim</span><span class="font-medium text-ink" x-text="formatRupiah(po.shipping_cost)"></span></div>
                                    <div class="flex items-center justify-between gap-4 text-muted"><span>Biaya lain</span><span class="font-medium text-ink" x-text="formatRupiah(po.other_cost)"></span></div>
                                    <div class="flex items-center justify-between gap-4 border-t border-line pt-2"><span class="font-semibold text-ink">Total PO</span><span class="text-lg font-bold tracking-[-0.025em] text-brand-800" x-text="formatRupiah(po.grand_total)"></span></div>
                                </div>
                            </section>

                            <section class="rounded-xl border border-line bg-white">
                                <div class="border-b border-line px-5 py-4 sm:px-6">
                                    <h2 class="font-semibold text-ink">Penerimaan barang terkait</h2>
                                </div>
                                <div x-show="receiptsLoading" class="p-5 text-sm text-muted">Memuat penerimaan barang...</div>
                                <div x-show="!receiptsLoading" class="overflow-x-auto">
                                    <table class="w-full min-w-[600px] text-left text-sm">
                                        <thead>
                                            <tr class="border-b border-line text-xs font-semibold text-muted">
                                                <th class="px-5 py-3 sm:px-6">Nomor</th>
                                                <th class="px-4 py-3">Tanggal</th>
                                                <th class="px-4 py-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-line">
                                            <template x-for="receipt in receipts" :key="receipt.id">
                                                <tr>
                                                    <td class="px-5 py-3 font-mono text-xs font-semibold text-brand-800 sm:px-6" x-text="receipt.receipt_number"></td>
                                                    <td class="px-4 py-3 text-muted" x-text="formatDate(receipt.receipt_date)"></td>
                                                    <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="receiptStatusClass(receipt.status)" x-text="receipt.status_label"></span></td>
                                                </tr>
                                            </template>
                                            <tr x-show="receipts.length === 0"><td colspan="3" class="px-5 py-8 text-center text-muted">Belum ada penerimaan barang untuk PO ini.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>
                    </template>
                </main>
            </div>
        </div>
    </body>
</html>
