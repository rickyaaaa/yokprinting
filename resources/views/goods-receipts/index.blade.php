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
        <meta name="description" content="Kelola Penerimaan Barang YokPrinting.ID">
        <title>Penerimaan Barang - YokPrinting.ID</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"></div>
            <x-app-sidebar />

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button type="button" class="btn-icon mr-3 border-transparent lg:hidden" @click="sidebarOpen = true" aria-label="Buka navigasi">
                        <i class="iconify tabler--align-left text-xl"></i>
                    </button>
                    <div class="flex items-center gap-2 text-sm">
                        <a href="{{ route('dashboard') }}" class="hidden text-muted hover:text-ink sm:inline">Dashboard</a>
                        <span class="hidden text-line sm:inline">/</span>
                        <span class="font-medium text-ink">Penerimaan Barang</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8" x-data="goodsReceiptIndexPage()">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <span class="badge badge-brand">Pembelian</span>
                            <h1 class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Penerimaan Barang</h1>
                            <p class="mt-1 text-sm leading-6 text-muted">Draft belum memengaruhi stok - stok dan layer HPP FIFO baru berubah setelah diposting.</p>
                        </div>
                        <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline">
                            Buat dari Purchase Order
                        </a>
                    </div>

                    <section class="card">
                        <form class="grid gap-3 border-b border-line p-5 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="applyFilters()">
                            <label class="sm:col-span-2 lg:col-span-1">
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Pencarian</span>
                                <input type="search" class="form-control" x-model="filters.search" placeholder="Nomor GR atau nomor PO">
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
                            <div class="flex items-end gap-3">
                                <button type="submit" class="btn btn-primary min-h-11 flex-1">Terapkan</button>
                                <button type="button" class="btn btn-outline min-h-11 flex-1" @click="resetFilters()">Reset</button>
                            </div>
                        </form>

                        <div x-show="error" x-cloak class="m-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></div>
                        <div x-show="loading" class="p-10 text-center text-sm text-muted">Memuat daftar penerimaan barang...</div>

                        <div x-show="!loading" class="overflow-x-auto">
                            <table class="w-full min-w-[900px] text-left text-sm">
                                <thead>
                                    <tr class="border-b border-line text-xs font-semibold text-muted">
                                        <th class="px-5 py-3">Nomor GR</th>
                                        <th class="px-5 py-3">PO Terkait</th>
                                        <th class="px-5 py-3">Tanggal</th>
                                        <th class="px-5 py-3">Status</th>
                                        <th class="px-5 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="receipt in receipts" :key="receipt.id">
                                        <tr class="align-top hover:bg-brand-50/40">
                                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs font-semibold text-brand-800" x-text="receipt.receipt_number"></td>
                                            <td class="px-5 py-4">
                                                <span class="block font-semibold text-ink" x-text="receipt.purchase_order?.po_number"></span>
                                                <span class="mt-0.5 block text-xs text-muted" x-text="receipt.purchase_order?.supplier_name"></span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-muted" x-text="formatDate(receipt.receipt_date)"></td>
                                            <td class="px-5 py-4">
                                                <span class="badge" :class="statusClass(receipt.status)" x-text="receipt.status_label"></span>
                                            </td>
                                            <td class="px-5 py-4 text-right">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <button
                                                        x-show="receipt.status === 'draft' && {{ $can('goods_receipt.post') ? 'true' : 'false' }}"
                                                        type="button"
                                                        class="btn btn-sm border-green-200 bg-green-50 text-green-800 hover:bg-green-100 disabled:cursor-wait"
                                                        :disabled="actingOn === receipt.id"
                                                        @click="post(receipt)"
                                                    >Posting</button>
                                                    <button
                                                        x-show="receipt.can_be_cancelled && {{ $can('goods_receipt.cancel') ? 'true' : 'false' }}"
                                                        type="button"
                                                        class="btn btn-sm btn-danger-outline disabled:cursor-wait"
                                                        :disabled="actingOn === receipt.id"
                                                        @click="cancel(receipt)"
                                                    >Batalkan</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="receipts.length === 0">
                                        <td colspan="5" class="px-5 py-12 text-center text-muted">Belum ada penerimaan barang yang sesuai filter.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <footer class="flex flex-col gap-3 border-t border-line px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-muted" x-text="`Total ${meta.total} penerimaan`"></p>
                            <div class="flex items-center gap-2">
                                <button type="button" class="btn btn-outline disabled:cursor-not-allowed" :disabled="meta.current_page <= 1 || loading" @click="goToPage(meta.current_page - 1)">Sebelumnya</button>
                                <span class="px-2 text-muted" x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span>
                                <button type="button" class="btn btn-outline disabled:cursor-not-allowed" :disabled="meta.current_page >= meta.last_page || loading" @click="goToPage(meta.current_page + 1)">Berikutnya</button>
                            </div>
                        </footer>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
