@php
    $profile = [
        'businessName' => 'Ruang Karya Digital',
        'legalName' => 'PT Ruang Karya Digital Indonesia',
        'businessType' => 'Perseroan Terbatas',
        'registrationNumber' => 'NIB-9120310045517',
        'industry' => 'Jasa kreatif & percetakan digital',
        'businessScale' => 'Perusahaan menengah',
        'foundedYear' => '2018',
        'picName' => 'Andi Pratama',
        'picRole' => 'Direktur Operasional',
        'email' => 'finance@ruangkarya.example',
        'phone' => '+62 21 5088 7721',
        'website' => 'https://ruangkarya.example',
        'taxNumber' => '09.876.543.2-101.000',
        'invoicePrefix' => 'INV',
        'bankName' => 'Bank Central Asia',
        'bankAccount' => '7721998877',
        'bankHolder' => 'PT Ruang Karya Digital Indonesia',
        'address' => 'Jl. Kemang Timur No. 88',
        'city' => 'Jakarta Selatan',
        'province' => 'DKI Jakarta',
        'postalCode' => '12730',
        'brandColor' => '#52772c',
        'invoiceTemplate' => 'professional',
        'defaultTaxRate' => 11,
        'defaultDueDays' => 14,
        'reminderDaysBeforeDue' => 3,
        'numberingReset' => 'yearly',
        'notes' => 'Pembayaran dilakukan maksimal sesuai tanggal jatuh tempo invoice.',
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Pengaturan profil usaha YokPrinting.ID">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Pengaturan Profil Usaha - YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" aria-hidden="true"></div>

            <x-app-sidebar />

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button type="button" class="btn-icon mr-3 border-transparent lg:hidden" @click="sidebarOpen = true" aria-controls="app-sidebar" :aria-expanded="sidebarOpen" aria-label="Buka navigasi">
                        <i class="iconify tabler--align-left text-xl"></i>
                    </button>
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <a href="{{ route('dashboard') }}" class="hidden text-muted hover:text-ink sm:inline">Dashboard</a>
                        <i class="iconify tabler--chevron-right hidden text-sm text-line sm:block"></i>
                        <span class="truncate font-medium text-ink">Pengaturan profil usaha</span>
                    </div>
                    <div class="ml-auto hidden text-sm text-muted sm:block">Jumat, 24 Juli 2026</div>
                </header>

                <main class="mx-auto w-full max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="badge badge-brand">Pengaturan</span>
                                <span class="badge badge-accent">Terhubung backend</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Pengaturan profil usaha</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Kelola identitas perusahaan, alamat, kontak, pajak, dan informasi pembayaran yang muncul di invoice.</p>
                        </div>
                    </div>

                    <nav class="mb-6 overflow-x-auto card p-2" aria-label="Navigasi pengaturan profil usaha">
                        <div class="flex min-w-max gap-1 text-sm font-semibold text-muted">
                            <a href="#company-info" class="rounded-lg px-3 py-2 hover:bg-brand-50 hover:text-brand-800">Profil perusahaan</a>
                            <a href="#default-invoice-settings" class="rounded-lg px-3 py-2 hover:bg-brand-50 hover:text-brand-800">Default invoice</a>
                            <a href="#brand-assets" class="rounded-lg px-3 py-2 hover:bg-brand-50 hover:text-brand-800">Logo & tema</a>
                            <a href="#settings-preview" class="rounded-lg px-3 py-2 hover:bg-brand-50 hover:text-brand-800">Preview & simpan</a>
                        </div>
                    </nav>

                    <form class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]" x-data='companyProfileSettings(@json($profile))' :style="themeLivePreviewStyle" @submit.prevent="submit()" novalidate>
                        <section class="space-y-6">
                            <div x-show="saved" x-cloak data-testid="company-profile-saved-notice" class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                                <p class="font-semibold">Profil usaha tersimpan.</p>
                                <p class="mt-1">Perubahan profil, default invoice, tema, dan logo sudah dikirim ke backend.</p>
                                <p class="mt-2 text-xs font-semibold" x-text="savedAt ? `Tersimpan pukul ${savedAt}` : ''"></p>
                            </div>

                            <div x-show="validationMessages.length > 0" x-cloak data-testid="company-profile-validation-summary" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                                <p class="font-semibold">Form pengaturan perlu diperiksa</p>
                                <ul class="mt-2 list-disc space-y-1 pl-5">
                                    <template x-for="message in validationMessages" :key="message">
                                        <li x-text="message"></li>
                                    </template>
                                </ul>
                            </div>

                            <section id="company-info" class="scroll-mt-24 card p-5 sm:p-6" aria-labelledby="business-identity-heading">
                                <h2 id="business-identity-heading" class="font-semibold text-ink">Formulir informasi perusahaan</h2>
                                <p class="mt-1 text-sm text-muted">Identitas usaha, legalitas, dan penanggung jawab utama yang tersimpan di backend.</p>
                                <div class="mt-5 grid gap-4 md:grid-cols-2">
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Nama usaha</span>
                                        <input class="form-control mt-1.5" x-model="form.businessName" data-validation-field="businessName" @input="clearFieldError('businessName')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.businessName" x-text="fieldErrors.businessName"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Nama legal perusahaan</span>
                                        <input class="form-control mt-1.5" x-model="form.legalName">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Jenis badan usaha</span>
                                        <select class="form-control mt-1.5" x-model="form.businessType">
                                            <option>Perseroan Terbatas</option>
                                            <option>CV</option>
                                            <option>Firma</option>
                                            <option>Perorangan</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Nomor legalitas / NIB</span>
                                        <input class="form-control mt-1.5" x-model="form.registrationNumber">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Bidang usaha</span>
                                        <input class="form-control mt-1.5" x-model="form.industry">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Skala bisnis</span>
                                        <select class="form-control mt-1.5" x-model="form.businessScale">
                                            <option>UMKM</option>
                                            <option>Perusahaan kecil</option>
                                            <option>Perusahaan menengah</option>
                                            <option>Enterprise</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Tahun berdiri</span>
                                        <input class="form-control mt-1.5" x-model="form.foundedYear">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Penanggung jawab</span>
                                        <input class="form-control mt-1.5" x-model="form.picName">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Jabatan penanggung jawab</span>
                                        <input class="form-control mt-1.5" x-model="form.picRole">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Email penagihan</span>
                                        <input type="email" class="form-control mt-1.5" x-model="form.email" data-validation-field="email" @input="clearFieldError('email')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.email" x-text="fieldErrors.email"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Nomor telepon</span>
                                        <input class="form-control mt-1.5" x-model="form.phone">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Website</span>
                                        <input class="form-control mt-1.5" x-model="form.website">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">NPWP / nomor pajak</span>
                                        <input class="form-control mt-1.5" x-model="form.taxNumber">
                                    </label>
                                </div>
                            </section>

                            <section class="card p-5 sm:p-6" aria-labelledby="business-address-heading">
                                <h2 id="business-address-heading" class="font-semibold text-ink">Alamat & pembayaran</h2>
                                <div class="mt-5 grid gap-4 md:grid-cols-2">
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-medium text-ink">Alamat usaha</span>
                                        <textarea class="form-control mt-1.5 min-h-24" x-model="form.address" data-validation-field="address" @input="clearFieldError('address')"></textarea>
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.address" x-text="fieldErrors.address"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Kota</span>
                                        <input class="form-control mt-1.5" x-model="form.city">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Provinsi</span>
                                        <input class="form-control mt-1.5" x-model="form.province">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Kode pos</span>
                                        <input class="form-control mt-1.5" x-model="form.postalCode">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Prefix invoice</span>
                                        <input class="form-control mt-1.5" x-model="form.invoicePrefix" data-validation-field="invoicePrefix" @input="clearFieldError('invoicePrefix')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.invoicePrefix" x-text="fieldErrors.invoicePrefix"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Nama bank</span>
                                        <input class="form-control mt-1.5" x-model="form.bankName">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Nomor rekening</span>
                                        <input class="form-control mt-1.5" x-model="form.bankAccount">
                                    </label>
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-medium text-ink">Catatan default invoice</span>
                                        <textarea class="form-control mt-1.5 min-h-24" x-model="form.notes"></textarea>
                                    </label>
                                </div>
                            </section>

                            <section id="default-invoice-settings" class="scroll-mt-24 card p-5 sm:p-6" aria-labelledby="default-settings-heading">
                                <h2 id="default-settings-heading" class="font-semibold text-ink">Pengaturan default invoice</h2>
                                <p class="mt-1 text-sm leading-6 text-muted">Atur template, PPN default, jatuh tempo, dan pengingat yang otomatis dipakai saat membuat invoice baru.</p>
                                <p class="sr-only">Opsi template tersedia: Professional clean, Modern compact, Creative bold.</p>
                                <div class="mt-5 grid gap-4 md:grid-cols-2">
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-medium text-ink">Template invoice default</span>
                                        <select class="form-control mt-1.5" x-model="form.invoiceTemplate">
                                            <template x-for="template in invoiceTemplateOptions" :key="template.key">
                                                <option :value="template.key" x-text="template.label"></option>
                                            </template>
                                        </select>
                                        <span class="mt-1 block text-xs text-muted" x-text="currentInvoiceTemplate.description"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">PPN default (%)</span>
                                        <input type="number" min="0" max="100" class="form-control mt-1.5" x-model.number="form.defaultTaxRate" data-validation-field="defaultTaxRate" :aria-invalid="Boolean(fieldErrors.defaultTaxRate)" @input="clearFieldError('defaultTaxRate')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.defaultTaxRate" x-text="fieldErrors.defaultTaxRate"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Jatuh tempo default</span>
                                        <div class="mt-1.5 flex items-center gap-2">
                                            <input type="number" min="0" class="form-control" x-model.number="form.defaultDueDays" data-validation-field="defaultDueDays" :aria-invalid="Boolean(fieldErrors.defaultDueDays)" @input="clearFieldError('defaultDueDays')">
                                            <span class="shrink-0 text-sm text-muted">hari</span>
                                        </div>
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.defaultDueDays" x-text="fieldErrors.defaultDueDays"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Pengingat sebelum jatuh tempo</span>
                                        <div class="mt-1.5 flex items-center gap-2">
                                            <input type="number" min="0" class="form-control" x-model.number="form.reminderDaysBeforeDue" data-validation-field="reminderDaysBeforeDue" :aria-invalid="Boolean(fieldErrors.reminderDaysBeforeDue)" @input="clearFieldError('reminderDaysBeforeDue')">
                                            <span class="shrink-0 text-sm text-muted">hari</span>
                                        </div>
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.reminderDaysBeforeDue" x-text="fieldErrors.reminderDaysBeforeDue"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Reset nomor invoice</span>
                                        <select class="form-control mt-1.5" x-model="form.numberingReset">
                                            <option value="yearly">Tahunan</option>
                                            <option value="monthly">Bulanan</option>
                                            <option value="never">Tidak otomatis</option>
                                        </select>
                                    </label>
                                </div>
                            </section>

                            <section id="brand-assets" class="scroll-mt-24 card p-5 sm:p-6" aria-labelledby="logo-upload-heading">
                                <h2 id="logo-upload-heading" class="font-semibold text-ink">Upload logo perusahaan</h2>
                                <p class="mt-1 text-sm leading-6 text-muted">Pilih file gambar untuk melihat pratinjau logo pada invoice dan simpan ke storage aplikasi.</p>
                                <div class="mt-5 grid gap-4 md:grid-cols-[12rem_minmax(0,1fr)] md:items-center">
                                    <div class="rounded-xl border border-dashed border-line bg-canvas p-4 text-center">
                                        <template x-if="logoPreview">
                                            <img :src="logoPreview" alt="Pratinjau logo perusahaan" class="mx-auto size-20 rounded-xl object-contain">
                                        </template>
                                        <template x-if="!logoPreview">
                                            <span class="mx-auto grid size-20 place-items-center rounded-xl text-lg font-bold text-white" :style="`background-color: ${form.brandColor}`" x-text="initials"></span>
                                        </template>
                                        <p class="mt-3 truncate text-xs font-medium text-muted" x-text="logoFileName || 'Belum ada file dipilih'"></p>
                                    </div>
                                    <div>
                                        <label class="block">
                                            <span class="text-sm font-medium text-ink">File logo</span>
                                            <input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="form-control mt-1.5" data-testid="company-logo-input" @change="handleLogoUpload($event)">
                                        </label>
                                        <p class="mt-2 text-xs leading-5 text-muted">Rekomendasi: PNG transparan atau SVG, rasio mendekati persegi, ukuran maksimal 2 MB.</p>
                                        <button type="button" class="btn btn-sm btn-outline mt-3" x-show="logoPreview" x-cloak @click="clearLogo()">
                                            Hapus logo
                                        </button>
                                    </div>
                                </div>
                            </section>

                            <section class="card p-5 sm:p-6" aria-labelledby="theme-palette-heading">
                                <h2 id="theme-palette-heading" class="font-semibold text-ink">Palet warna tema invoice</h2>
                                <p class="mt-1 text-sm leading-6 text-muted">Pilih warna utama untuk logo fallback, aksen invoice, dan tombol brand di dokumen pelanggan.</p>
                                <p class="sr-only">Pilihan palet tersedia: Sage profesional, Ocean blue, Sunset amber, Ink premium.</p>
                                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                    <template x-for="palette in themePalettes" :key="palette.key">
                                        <button
                                            type="button"
                                            class="flex items-center gap-3 rounded-xl border p-3 text-left transition hover:bg-brand-50"
                                            :class="selectedPalette === palette.key ? 'border-brand-600 ring-2 ring-brand-100' : 'border-line'"
                                            :aria-pressed="selectedPalette === palette.key"
                                            @click="selectPalette(palette)"
                                        >
                                            <span class="flex -space-x-1" aria-hidden="true">
                                                <span class="size-7 rounded-full ring-2 ring-white" :style="`background-color: ${palette.primary}`"></span>
                                                <span class="size-7 rounded-full ring-2 ring-white" :style="`background-color: ${palette.accent}`"></span>
                                                <span class="size-7 rounded-full ring-2 ring-white" :style="`background-color: ${palette.soft}`"></span>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-semibold text-ink" x-text="palette.label"></span>
                                                <span class="block text-xs text-muted" x-text="palette.description"></span>
                                            </span>
                                        </button>
                                    </template>
                                </div>
                                <div class="mt-5 rounded-xl border border-line p-4" :style="`background: linear-gradient(135deg, ${currentPalette.soft}, #ffffff)`">
                                    <p class="text-xs font-semibold text-muted">Preview aksen tema</p>
                                    <div class="mt-3 flex items-center justify-between gap-3 rounded-lg bg-white p-3">
                                        <span class="font-semibold text-ink" x-text="currentPalette.label"></span>
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold text-white" :style="`background-color: ${form.brandColor}`">Aktif</span>
                                    </div>
                                </div>
                            </section>
                        </section>

                        <aside id="settings-preview" class="scroll-mt-24 space-y-6">
                            <section class="card p-5 sm:p-6" aria-labelledby="profile-preview-heading">
                                <h2 id="profile-preview-heading" class="font-semibold text-ink">Preview profil invoice</h2>
                                <div class="mt-5 rounded-xl border border-line bg-canvas p-4">
                                    <div class="flex items-center gap-3">
                                        <template x-if="logoPreview">
                                            <img :src="logoPreview" alt="Logo invoice" class="size-12 rounded-xl object-contain ring-1 ring-line">
                                        </template>
                                        <template x-if="!logoPreview">
                                            <span class="grid size-12 place-items-center rounded-xl text-sm font-bold text-white" :style="`background-color: ${form.brandColor}`" x-text="initials"></span>
                                        </template>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-ink" x-text="form.businessName"></p>
                                            <p class="mt-0.5 text-xs text-muted" x-text="form.email"></p>
                                        </div>
                                    </div>
                                    <dl class="mt-5 space-y-3 text-sm">
                                        <div class="rounded-lg border border-line bg-white p-3" :style="`border-top: 4px solid ${form.brandColor}`">
                                            <dt class="text-xs font-medium text-muted">Tema invoice</dt>
                                            <dd class="mt-1 font-semibold text-ink" x-text="currentPalette.label"></dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-muted">Alamat invoice</dt>
                                            <dd class="mt-1 leading-6 text-ink" x-text="fullAddress"></dd>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted">Jenis usaha</dt>
                                            <dd class="font-medium text-ink" x-text="form.businessType"></dd>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted">Bidang usaha</dt>
                                            <dd class="font-medium text-ink" x-text="form.industry"></dd>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted">Nomor pajak</dt>
                                            <dd class="font-medium text-ink" x-text="form.taxNumber || '-'"></dd>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted">Nomor invoice contoh</dt>
                                            <dd class="font-mono text-xs font-semibold text-ink" x-text="sampleInvoiceNumber"></dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-muted">Rekening pembayaran</dt>
                                            <dd class="mt-1 font-medium text-ink" x-text="paymentLine"></dd>
                                        </div>
                                    </dl>
                                </div>
                            </section>

                            <section class="rounded-xl border border-brand-200 bg-brand-50 p-5 text-sm text-brand-900">
                                <h2 class="font-semibold">Catatan workflow</h2>
                                <p class="mt-3 leading-6">Form ini sekarang menyimpan profil perusahaan, logo, palet tema, template invoice, pajak, jatuh tempo, dan aturan nomor invoice ke backend.</p>
                            </section>

                            <section class="card p-5 sm:p-6" aria-labelledby="theme-live-preview-heading">
                                <h2 id="theme-live-preview-heading" class="font-semibold text-ink">Pratinjau langsung warna tema</h2>
                                <p class="mt-1 text-sm leading-6 text-muted">Mock invoice ini berubah langsung mengikuti palet yang dipilih.</p>
                                <div data-testid="theme-live-preview" class="mt-5 overflow-hidden rounded-xl border border-line">
                                    <div class="p-4 text-white" style="background-color: var(--theme-primary)">
                                        <div class="flex items-center justify-between gap-4">
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-[0.18em] opacity-80">Invoice</p>
                                                <p class="mt-1 font-semibold" x-text="sampleInvoiceNumber"></p>
                                                <p class="mt-1 text-xs opacity-80" x-text="currentInvoiceTemplate.label"></p>
                                            </div>
                                            <span class="rounded-full px-3 py-1 text-xs font-semibold" style="background-color: var(--theme-accent)">Draft</span>
                                        </div>
                                    </div>
                                    <div class="space-y-4 p-4" style="background: linear-gradient(180deg, var(--theme-soft), #ffffff 55%)">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-ink" x-text="form.businessName"></p>
                                                <p class="mt-1 text-xs text-muted" x-text="form.email"></p>
                                            </div>
                                            <span class="rounded-lg px-2.5 py-1 text-xs font-semibold" style="background-color: var(--theme-soft); color: var(--theme-primary)" x-text="currentPalette.label"></span>
                                        </div>
                                        <div class="rounded-lg bg-white p-3 text-sm border border-line">
                                            <div class="flex justify-between gap-3">
                                                <span class="text-muted">Cetak katalog premium</span>
                                                <span class="font-semibold text-ink">Rp6.000.000</span>
                                            </div>
                                            <div class="mt-2 flex justify-between gap-3 text-xs text-muted">
                                                <span>PPN default</span>
                                                <span x-text="`${form.defaultTaxRate}%`"></span>
                                            </div>
                                            <div class="mt-1 flex justify-between gap-3 text-xs text-muted">
                                                <span>Jatuh tempo</span>
                                                <span x-text="`${form.defaultDueDays} hari`"></span>
                                            </div>
                                            <div class="mt-2 h-1.5 rounded-full" style="background-color: var(--theme-soft)">
                                                <div class="h-1.5 w-2/3 rounded-full" style="background-color: var(--theme-primary)"></div>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between rounded-lg px-3 py-2 text-white" style="background-color: var(--theme-primary)">
                                            <span class="text-sm font-medium">Total tagihan</span>
                                            <span class="font-semibold">Rp6.660.000</span>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <div class="card p-5">
                                <button type="submit" class="btn btn-primary w-full" :disabled="saving">
                                    <i x-show="saving" class="iconify tabler--loader-2 animate-spin text-base"></i>
                                    <span x-text="saving ? 'Menyimpan...' : 'Simpan profil usaha'"></span>
                                </button>
                                <a href="{{ route('dashboard') }}" class="btn btn-outline w-full mt-3">
                                    Kembali ke dashboard
                                </a>
                            </div>
                        </aside>
                    </form>
                </main>
            </div>
        </div>
    </body>
</html>
