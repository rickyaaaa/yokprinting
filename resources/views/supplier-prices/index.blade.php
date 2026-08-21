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
        <meta name="description" content="Histori harga supplier YokPrinting.ID">
        <title>Harga Supplier - YokPrinting.ID</title>
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
                        <span class="font-medium text-ink">Harga Supplier</span>
                    </div>
                </header>

                <main
                    class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                    x-data='supplierPriceIndexPage(@json(["productId" => request()->query("product_id", "")]))'
                >
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <span class="badge badge-brand">Pembelian</span>
                            <h1 class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Harga Supplier</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Histori harga yang ditawarkan supplier - referensi saat membuat PO, bukan harga transaksi. Setiap harga baru dari supplier dicatat sebagai entri baru, histori lama tidak pernah dihapus.</p>
                        </div>
                        @if ($can('supplier_price.create'))
                            <a href="{{ route('supplier-prices.create') }}" class="btn btn-primary">
                                <i class="iconify tabler--plus text-base"></i>
                                Tambah harga supplier
                            </a>
                        @endif
                    </div>

                    <section class="card">
                        <form class="grid gap-3 border-b border-line p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6" @submit.prevent="applyFilters()">
                            <label class="sm:col-span-2 lg:col-span-1">
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Pencarian</span>
                                <input type="search" class="form-control" x-model="filters.search" placeholder="Catatan, referensi, supplier, produk">
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Supplier</span>
                                <select class="form-control" x-model="filters.supplier_id">
                                    <option value="">Semua supplier</option>
                                    <template x-for="supplier in suppliers" :key="supplier.id">
                                        <option :value="supplier.id" x-text="supplier.name"></option>
                                    </template>
                                </select>
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Produk</span>
                                <select class="form-control" x-model="filters.product_id">
                                    <option value="">Semua produk</option>
                                    <template x-for="product in products" :key="product.id">
                                        <option :value="product.id" x-text="`${product.sku} - ${product.name}`"></option>
                                    </template>
                                </select>
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
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Berlaku dari</span>
                                <input type="date" class="form-control" x-model="filters.date_from">
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Berlaku sampai</span>
                                <input type="date" class="form-control" x-model="filters.date_to">
                            </label>
                            <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-3 xl:col-span-6">
                                <button type="submit" class="btn btn-primary min-h-11 flex-1 sm:flex-none sm:px-8">Terapkan</button>
                                <button type="button" class="btn btn-outline min-h-11 flex-1 sm:flex-none sm:px-8" @click="resetFilters()">Reset</button>
                            </div>
                        </form>

                        <div x-show="error" x-cloak class="m-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></div>
                        <div x-show="loading" class="p-10 text-center text-sm text-muted">Memuat harga supplier...</div>

                        <div x-show="!loading" class="overflow-x-auto">
                            <table class="w-full min-w-[1100px] text-left text-sm">
                                <thead>
                                    <tr class="border-b border-line text-xs font-semibold text-muted">
                                        <th class="px-5 py-3">Supplier</th>
                                        <th class="px-5 py-3">Produk</th>
                                        <th class="px-5 py-3 text-right">Harga</th>
                                        <th class="px-5 py-3">Berlaku Mulai</th>
                                        <th class="px-5 py-3">Berlaku Sampai</th>
                                        <th class="px-5 py-3">Status</th>
                                        <th class="px-5 py-3">Catatan</th>
                                        <th class="px-5 py-3">Dibuat oleh</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="priceList in priceLists" :key="priceList.id">
                                        <tr class="align-top hover:bg-brand-50/40">
                                            <td class="px-5 py-4 text-ink" x-text="priceList.supplier?.name ?? '-'"></td>
                                            <td class="px-5 py-4 text-ink">
                                                <span class="block font-medium" x-text="priceList.product?.name ?? '-'"></span>
                                                <span class="block font-mono text-xs text-muted" x-text="priceList.product?.sku ?? ''"></span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-ink" x-text="formatRupiah(priceList.price)"></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-muted" x-text="formatDate(priceList.valid_from)"></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-muted" x-text="priceList.valid_until ? formatDate(priceList.valid_until) : 'Seterusnya'"></td>
                                            <td class="px-5 py-4">
                                                <span class="badge" :class="statusClass(priceList.status)" x-text="priceList.status_label"></span>
                                                <span x-show="expiryWarning(priceList)" class="mt-1 block text-xs font-medium text-amber-700" x-text="expiryWarning(priceList)"></span>
                                            </td>
                                            <td class="max-w-[220px] px-5 py-4 text-muted" x-text="priceList.notes || '-'"></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-muted" x-text="priceList.created_by ?? '-'"></td>
                                        </tr>
                                    </template>
                                    <tr x-show="priceLists.length === 0">
                                        <td colspan="8" class="px-5 py-12 text-center text-muted">Belum ada harga supplier yang sesuai filter.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <footer class="flex flex-col gap-3 border-t border-line px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-muted" x-text="`Total ${meta.total} entri harga`"></p>
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
