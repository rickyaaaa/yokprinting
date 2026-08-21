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
        <meta name="description" content="Kelola Purchase Order YokPrinting.ID">
        <title>Purchase Order - YokPrinting.ID</title>
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
                        <a href="{{ route('dashboard') }}" class="hidden text-muted hover:text-ink sm:inline">Dashboard</a>
                        <span class="hidden text-line sm:inline">/</span>
                        <span class="font-medium text-ink">Purchase Order</span>
                    </div>
                </header>

                <main
                    class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                    x-data="purchaseOrderIndexPage()"
                >
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Pembelian</span>
                            <h1 class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Purchase Order</h1>
                            <p class="mt-1 text-sm leading-6 text-muted">Harga di setiap PO terkunci permanen sejak dibuat - tidak berubah walau harga barang terbaru berubah.</p>
                        </div>
                        @if ($can('purchase_order.create'))
                            <a href="{{ route('purchase-orders.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">
                                <span class="text-lg leading-none">+</span>
                                Buat PO baru
                            </a>
                        @endif
                    </div>

                    <section class="rounded-xl border border-line bg-white">
                        <form class="grid gap-3 border-b border-line p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" @submit.prevent="applyFilters()">
                            <label class="sm:col-span-2 lg:col-span-1">
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Pencarian</span>
                                <input type="search" class="form-control" x-model="filters.search" placeholder="Nomor PO atau nama supplier">
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Status</span>
                                <select class="form-control" x-model="filters.status">
                                    <option value="">Semua status</option>
                                    <template x-for="(label, value) in statusLabels" :key="value">
                                        <option :value="value" x-text="label"></option>
                                    </template>
                                </select>
                            </label>
                            <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-1">
                                <button type="submit" class="min-h-11 flex-1 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Terapkan</button>
                                <button type="button" class="min-h-11 flex-1 rounded-lg border border-line px-4 py-2.5 text-sm font-semibold text-muted hover:bg-canvas" @click="resetFilters()">Reset</button>
                            </div>
                        </form>

                        <div x-show="error" x-cloak class="m-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></div>
                        <div x-show="loading" class="p-10 text-center text-sm text-muted">Memuat daftar PO...</div>

                        <div x-show="!loading" class="overflow-x-auto">
                            <table class="w-full min-w-[1000px] text-left text-sm">
                                <thead>
                                    <tr class="border-b border-line text-xs font-semibold text-muted">
                                        <th class="px-5 py-3">Nomor PO</th>
                                        <th class="px-5 py-3">Supplier</th>
                                        <th class="px-5 py-3">Tanggal</th>
                                        <th class="px-5 py-3 text-right">Total</th>
                                        <th class="px-5 py-3">Status</th>
                                        <th class="px-5 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="po in purchaseOrders" :key="po.id">
                                        <tr class="align-top hover:bg-brand-50/40">
                                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs font-semibold">
                                                <a :href="`/purchase-orders/${po.id}`" class="text-brand-800 hover:text-brand-900 hover:underline" x-text="po.po_number"></a>
                                            </td>
                                            <td class="px-5 py-4 text-ink" x-text="po.supplier?.name ?? '-' "></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-muted" x-text="formatDate(po.order_date)"></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-ink" x-text="formatRupiah(po.grand_total)"></td>
                                            <td class="px-5 py-4">
                                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(po.status)" x-text="po.status_label"></span>
                                            </td>
                                            <td class="px-5 py-4 text-right">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <button
                                                        x-show="po.status === 'draft' && {{ $can('purchase_order.submit') ? 'true' : 'false' }}"
                                                        type="button"
                                                        class="rounded-lg border border-line px-3 py-1.5 text-xs font-semibold text-ink hover:bg-canvas disabled:cursor-wait disabled:opacity-60"
                                                        :disabled="actingOn === po.id"
                                                        @click="submitForApproval(po)"
                                                    >Ajukan</button>
                                                    <button
                                                        x-show="po.status === 'waiting_approval' && {{ $can('purchase_order.approve') ? 'true' : 'false' }}"
                                                        type="button"
                                                        class="rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-800 hover:bg-green-100 disabled:cursor-wait disabled:opacity-60"
                                                        :disabled="actingOn === po.id"
                                                        @click="approve(po)"
                                                    >Setujui</button>
                                                    <a
                                                        x-show="['approved', 'partially_received'].includes(po.status) && {{ $can('goods_receipt.create') ? 'true' : 'false' }}"
                                                        :href="`{{ route('goods-receipts.create') }}?purchase_order_id=${po.id}`"
                                                        class="rounded-lg border border-line px-3 py-1.5 text-xs font-semibold text-ink hover:bg-canvas"
                                                    >Terima Barang</a>
                                                    <button
                                                        x-show="po.can_be_cancelled && {{ $can('purchase_order.cancel') ? 'true' : 'false' }}"
                                                        type="button"
                                                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:cursor-wait disabled:opacity-60"
                                                        :disabled="actingOn === po.id"
                                                        @click="cancel(po)"
                                                    >Batalkan</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="purchaseOrders.length === 0">
                                        <td colspan="6" class="px-5 py-12 text-center text-muted">Belum ada PO yang sesuai filter.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <footer class="flex flex-col gap-3 border-t border-line px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-muted" x-text="`Total ${meta.total} PO`"></p>
                            <div class="flex items-center gap-2">
                                <button type="button" class="rounded-lg border border-line px-3 py-2 font-semibold disabled:cursor-not-allowed disabled:opacity-40" :disabled="meta.current_page <= 1 || loading" @click="goToPage(meta.current_page - 1)">Sebelumnya</button>
                                <span class="px-2 text-muted" x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span>
                                <button type="button" class="rounded-lg border border-line px-3 py-2 font-semibold disabled:cursor-not-allowed disabled:opacity-40" :disabled="meta.current_page >= meta.last_page || loading" @click="goToPage(meta.current_page + 1)">Berikutnya</button>
                            </div>
                        </footer>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
