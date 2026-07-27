@php
    $customerCode = $customerCode ?? null;
    $customers = [
        'CUS-001' => ['code' => 'CUS-001', 'name' => 'PT Sinar Nusantara', 'segment' => 'Enterprise', 'email' => 'finance@sinarnusantara.co.id', 'phone' => '+62 21 555 0198', 'taxNumber' => '09.123.456.7-012.000', 'address' => 'Jl. Kemang Raya No. 18', 'city' => 'Jakarta Selatan', 'province' => 'DKI Jakarta', 'postalCode' => '12730', 'status' => 'Aktif', 'notes' => 'Prioritas follow-up untuk invoice desain brand dan katalog.'],
        'CUS-002' => ['code' => 'CUS-002', 'name' => 'CV Lautan Rasa', 'segment' => 'UMKM', 'email' => 'billing@lautanrasa.example', 'phone' => '+62 361 700 210', 'taxNumber' => '08.772.110.3-904.000', 'address' => 'Jl. Danau Tamblingan No. 42', 'city' => 'Denpasar', 'province' => 'Bali', 'postalCode' => '80228', 'status' => 'Aktif', 'notes' => 'Sering memesan materi promosi musiman.'],
        'CUS-003' => ['code' => 'CUS-003', 'name' => 'PT Bumi Lestari', 'segment' => 'Corporate', 'email' => 'finance@bumilestari.example', 'phone' => '+62 22 7788 440', 'taxNumber' => '04.551.320.8-441.000', 'address' => 'Jl. Asia Afrika No. 77', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'postalCode' => '40111', 'status' => 'Perlu follow-up', 'notes' => 'Perlu dihubungi terkait invoice overdue.'],
    ];
    $isEdit = $customerCode !== null;
    $customer = $customers[$customerCode] ?? ['code' => '', 'name' => '', 'email' => '', 'phone' => '', 'taxNumber' => '', 'address' => '', 'city' => '', 'province' => '', 'postalCode' => '', 'status' => 'Aktif', 'notes' => ''];
    $title = $isEdit ? 'Edit pelanggan '.$customer['code'] : 'Tambah pelanggan baru';
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Form pelanggan YokPrinting.ID">

        <title>{{ $title }} - YokPrinting.ID</title>

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
                    <button type="button" class="mr-3 rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800 lg:hidden" @click="sidebarOpen = true" aria-controls="app-sidebar" :aria-expanded="sidebarOpen" aria-label="Buka navigasi">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <a href="{{ route('customers.index') }}" class="hidden text-muted hover:text-ink sm:inline">Data pelanggan</a>
                        <svg class="hidden size-4 text-line sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="truncate font-medium text-ink">{{ $isEdit ? 'Edit pelanggan' : 'Tambah pelanggan' }}</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <span class="hidden text-sm text-muted sm:inline">Jumat, 24 Juli 2026</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1180px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Data pelanggan</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">{{ $title }}</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Simpan informasi identitas, kontak, pajak, dan alamat pelanggan untuk kebutuhan invoice berikutnya.</p>
                        </div>
                        <a href="{{ route('customers.index') }}" class="inline-flex w-fit items-center gap-2 rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Kembali ke daftar
                        </a>
                    </div>

                    <form
                        class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]"
                        x-data='customerForm(@json($customer), @json($isEdit))'
                        @submit.prevent="submit()"
                        novalidate
                    >
                        <section class="space-y-6">
                            <div
                                x-show="saved"
                                x-cloak
                                data-testid="customer-saved-notice"
                                class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900"
                            >
                                <p class="font-semibold" x-text="isEdit ? 'Perubahan pelanggan tersimpan.' : 'Pelanggan baru tersimpan.'"></p>
                                <p class="mt-1">Kode pelanggan dibuat otomatis oleh sistem setelah data tersimpan.</p>
                            </div>

                            <div
                                x-show="validationMessages.length > 0"
                                x-cloak
                                data-testid="customer-validation-summary"
                                class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900"
                            >
                                <p class="font-semibold">Form pelanggan perlu diperiksa</p>
                                <ul class="mt-2 list-disc space-y-1 pl-5">
                                    <template x-for="message in validationMessages" :key="message">
                                        <li x-text="message"></li>
                                    </template>
                                </ul>
                            </div>

                            <section class="rounded-xl bg-white p-5 border border-line sm:p-6" aria-labelledby="customer-identity-heading">
                                <h2 id="customer-identity-heading" class="font-semibold text-ink">Identitas pelanggan</h2>
                                <div class="mt-5 grid gap-4 md:grid-cols-2">
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Nama pelanggan</span>
                                        <input class="form-control mt-1.5" x-model="form.name" data-validation-field="name" placeholder="Contoh: PT Sinar Nusantara" :aria-invalid="Boolean(fieldErrors.name)" @input="clearFieldError('name')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.name" x-text="fieldErrors.name"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Status</span>
                                        <select class="form-control mt-1.5" x-model="form.status">
                                            <option>Aktif</option>
                                            <option>Perlu follow-up</option>
                                            <option>Prospek</option>
                                            <option>Nonaktif</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Email</span>
                                        <input type="email" class="form-control mt-1.5" x-model="form.email" data-validation-field="email" placeholder="finance@example.com" :aria-invalid="Boolean(fieldErrors.email)" @input="clearFieldError('email')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.email" x-text="fieldErrors.email"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Telepon</span>
                                        <input class="form-control mt-1.5" x-model="form.phone" data-validation-field="phone" placeholder="+62 ..." :aria-invalid="Boolean(fieldErrors.phone)" @input="clearFieldError('phone')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.phone" x-text="fieldErrors.phone"></span>
                                    </label>
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-medium text-ink">NPWP / nomor pajak</span>
                                        <input class="form-control mt-1.5" x-model="form.taxNumber" placeholder="Opsional">
                                    </label>
                                </div>
                            </section>

                            <section class="rounded-xl bg-white p-5 border border-line sm:p-6" aria-labelledby="customer-address-heading">
                                <h2 id="customer-address-heading" class="font-semibold text-ink">Alamat penagihan</h2>
                                <div class="mt-5 grid gap-4 md:grid-cols-2">
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-medium text-ink">Alamat</span>
                                        <textarea class="form-control mt-1.5 min-h-24" x-model="form.address" data-validation-field="address" placeholder="Alamat lengkap untuk invoice" :aria-invalid="Boolean(fieldErrors.address)" @input="clearFieldError('address')"></textarea>
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.address" x-text="fieldErrors.address"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Kota</span>
                                        <input class="form-control mt-1.5" x-model="form.city" data-validation-field="city" :aria-invalid="Boolean(fieldErrors.city)" @input="clearFieldError('city')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.city" x-text="fieldErrors.city"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Provinsi</span>
                                        <input class="form-control mt-1.5" x-model="form.province">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Kode pos</span>
                                        <input class="form-control mt-1.5" x-model="form.postalCode">
                                    </label>
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-medium text-ink">Catatan internal</span>
                                        <textarea class="form-control mt-1.5 min-h-24" x-model="form.notes" placeholder="Preferensi penagihan, PIC, atau konteks follow-up"></textarea>
                                    </label>
                                </div>
                            </section>
                        </section>

                        <aside class="space-y-6">
                            <section class="rounded-xl bg-white p-5 border border-line sm:p-6" aria-labelledby="customer-preview-heading">
                                <h2 id="customer-preview-heading" class="font-semibold text-ink">Preview kartu pelanggan</h2>
                                <div class="mt-5 rounded-xl border border-line bg-canvas p-4">
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-11 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-800" x-text="initials"></span>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-ink" x-text="form.name || 'Nama pelanggan'"></p>
                                            <p class="mt-0.5 font-mono text-xs text-muted" x-text="form.code || 'Kode dibuat otomatis'"></p>
                                        </div>
                                    </div>
                                    <dl class="mt-5 space-y-3 text-sm">
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted">Status</dt>
                                            <dd><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(form.status)" x-text="form.status"></span></dd>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted">Kota</dt>
                                            <dd class="font-medium text-ink" x-text="form.city || '-'"></dd>
                                        </div>
                                    </dl>
                                </div>
                            </section>

                            <section class="rounded-xl border border-brand-200 bg-brand-50 p-5 text-sm text-brand-900">
                                <h2 class="font-semibold">Catatan workflow</h2>
                                <p class="mt-3 leading-6">Kode pelanggan dibuat otomatis berurutan, jadi admin cukup mengisi identitas dan kontak pelanggan.</p>
                            </section>

                            <div class="rounded-xl bg-white p-5 border border-line">
                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-60" :disabled="saving">
                                    <svg x-show="saving" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M12 3a9 9 0 1 1-9 9" stroke-linecap="round"/>
                                    </svg>
                                    <span x-text="saving ? 'Menyimpan...' : '{{ $isEdit ? 'Simpan perubahan' : 'Simpan pelanggan' }}'"></span>
                                </button>
                                <a href="{{ route('customers.index') }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                                    Batal
                                </a>
                            </div>
                        </aside>
                    </form>
                </main>
            </div>
        </div>
    </body>
</html>
