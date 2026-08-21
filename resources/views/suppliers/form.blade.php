@php
    $isEdit = isset($supplier);
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $isEdit ? 'Ubah data supplier' : 'Tambah supplier baru' }} YokPrinting.ID">
        <title>{{ $isEdit ? 'Ubah Supplier' : 'Tambah Supplier' }} - YokPrinting.ID</title>
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
                        <a href="{{ route('suppliers.index') }}" class="hidden text-muted hover:text-ink sm:inline">Supplier</a>
                        <span class="hidden text-line sm:inline">/</span>
                        <span class="font-medium text-ink">{{ $isEdit ? 'Ubah' : 'Tambah baru' }}</span>
                    </div>
                </header>

                <main
                    class="mx-auto w-full max-w-[720px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                    x-data='supplierFormPage(@json(["isEdit" => $isEdit, "supplierId" => $isEdit ? $supplier->id : null]))'
                    x-init="init()"
                >
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <span class="badge badge-brand">Data bisnis</span>
                            <h1 class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">{{ $isEdit ? 'Ubah Supplier' : 'Tambah Supplier' }}</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Data supplier dipakai sebagai pilihan saat membuat Purchase Order dan mencatat Harga Supplier.</p>
                        </div>
                        <a href="{{ route('suppliers.index') }}" class="btn btn-outline w-fit">
                            <i class="iconify tabler--chevron-left text-base"></i>
                            Kembali ke daftar
                        </a>
                    </div>

                    <div x-show="generalError" x-cloak class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" x-text="generalError" role="alert"></div>
                    <div x-show="loading" class="mb-4 card p-4 text-sm text-muted">Memuat data supplier...</div>

                    <form class="space-y-6" @submit.prevent="submit()" x-show="!loading">
                        <section class="card p-5 sm:p-6">
                            <h2 class="font-semibold text-ink">Detail supplier</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Kode supplier</span>
                                    <input type="text" class="form-control" x-model="form.code" @input="clearError('code')" placeholder="Contoh: SUP-001">
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.code" x-text="errors.code"></span>
                                </label>
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Nama supplier</span>
                                    <input type="text" class="form-control" x-model="form.name" @input="clearError('name')" placeholder="Contoh: PT ABC Supplier">
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.name" x-text="errors.name"></span>
                                </label>
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Nama kontak <span class="font-normal text-muted">(opsional)</span></span>
                                    <input type="text" class="form-control" x-model="form.contact_person" @input="clearError('contact_person')">
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.contact_person" x-text="errors.contact_person"></span>
                                </label>
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Telepon <span class="font-normal text-muted">(opsional)</span></span>
                                    <input type="text" class="form-control" x-model="form.phone" @input="clearError('phone')">
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.phone" x-text="errors.phone"></span>
                                </label>
                                <label class="sm:col-span-2">
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Email <span class="font-normal text-muted">(opsional)</span></span>
                                    <input type="email" class="form-control" x-model="form.email" @input="clearError('email')">
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.email" x-text="errors.email"></span>
                                </label>
                                <label class="sm:col-span-2">
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Alamat <span class="font-normal text-muted">(opsional)</span></span>
                                    <textarea class="form-control min-h-24" x-model="form.address" @input="clearError('address')"></textarea>
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.address" x-text="errors.address"></span>
                                </label>
                            </div>
                        </section>

                        <div class="card p-5 sm:p-6">
                            <button
                                type="submit"
                                class="btn btn-primary w-full disabled:cursor-wait"
                                :disabled="saving"
                                x-text="saving ? 'Menyimpan...' : (isEdit ? 'Simpan perubahan' : 'Simpan supplier')"
                            ></button>
                        </div>
                    </form>
                </main>
            </div>
        </div>
    </body>
</html>
