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
        <meta name="description" content="Kelola data supplier YokPrinting.ID">
        <title>Supplier - YokPrinting.ID</title>
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
                        <span class="font-medium text-ink">Supplier</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1300px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8" x-data="supplierIndexPage()">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <span class="badge badge-brand">Data bisnis</span>
                            <h1 class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Supplier</h1>
                            <p class="mt-1 text-sm leading-6 text-muted">Daftar supplier yang dipakai saat membuat Purchase Order dan Harga Supplier.</p>
                        </div>
                        @if ($can('product.create'))
                            <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
                                <i class="iconify tabler--plus text-base"></i>
                                Tambah supplier
                            </a>
                        @endif
                    </div>

                    <section class="card">
                        <form class="flex flex-col gap-3 border-b border-line p-5 sm:flex-row" @submit.prevent="applySearch()">
                            <input type="search" class="form-control flex-1" x-model="search" placeholder="Cari kode, nama, kontak, telepon, atau email">
                            <button type="submit" class="btn btn-primary min-h-11">Cari</button>
                        </form>

                        <div x-show="error" x-cloak class="m-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></div>
                        <div x-show="loading" class="p-10 text-center text-sm text-muted">Memuat daftar supplier...</div>

                        <div x-show="!loading" class="overflow-x-auto">
                            <table class="w-full min-w-[900px] text-left text-sm">
                                <thead>
                                    <tr class="border-b border-line text-xs font-semibold text-muted">
                                        <th class="px-5 py-3">Kode</th>
                                        <th class="px-5 py-3">Nama</th>
                                        <th class="px-5 py-3">Kontak</th>
                                        <th class="px-5 py-3">Telepon</th>
                                        <th class="px-5 py-3">Email</th>
                                        <th class="px-5 py-3 text-right">Produk</th>
                                        <th class="px-5 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="supplier in suppliers" :key="supplier.id">
                                        <tr class="align-top hover:bg-brand-50/40">
                                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs font-semibold text-brand-800" x-text="supplier.code"></td>
                                            <td class="px-5 py-4 font-medium text-ink" x-text="supplier.name"></td>
                                            <td class="px-5 py-4 text-muted" x-text="supplier.contact_person || '-'"></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-muted" x-text="supplier.phone || '-'"></td>
                                            <td class="px-5 py-4 text-muted" x-text="supplier.email || '-'"></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right text-muted" x-text="supplier.products_count ?? 0"></td>
                                            <td class="px-5 py-4 text-right">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <a
                                                        x-show="{{ $can('product.update') ? 'true' : 'false' }}"
                                                        :href="`/suppliers/${supplier.id}/edit`"
                                                        class="btn btn-sm btn-outline"
                                                    >Ubah</a>
                                                    <button
                                                        x-show="{{ $can('product.delete') ? 'true' : 'false' }}"
                                                        type="button"
                                                        class="btn btn-sm btn-danger-outline disabled:cursor-wait"
                                                        :disabled="deletingId === supplier.id"
                                                        @click="remove(supplier)"
                                                    >Hapus</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="suppliers.length === 0">
                                        <td colspan="7" class="px-5 py-12 text-center text-muted">Belum ada supplier. Tambahkan supplier pertama untuk mulai membuat Purchase Order.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
