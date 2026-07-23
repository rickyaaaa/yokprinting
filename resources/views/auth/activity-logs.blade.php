@php
    $logs = [
        ['time' => '24 Jul 2026, 09:42', 'actor' => 'Andi Pratama', 'role' => 'Owner', 'module' => 'Role', 'type' => 'Update', 'activity' => 'Mengubah permission Admin Finance', 'ip' => '103.22.18.91', 'risk' => 'Sedang'],
        ['time' => '24 Jul 2026, 09:18', 'actor' => 'Maya Lestari', 'role' => 'Admin Finance', 'module' => 'Invoice', 'type' => 'Create', 'activity' => 'Membuat draft invoice INV-2026-0086', 'ip' => '103.22.18.94', 'risk' => 'Rendah'],
        ['time' => '24 Jul 2026, 08:55', 'actor' => 'Riko Saputra', 'role' => 'Sales', 'module' => 'Customer', 'type' => 'Update', 'activity' => 'Memperbarui data PT Sinar Nusantara', 'ip' => '103.22.18.102', 'risk' => 'Rendah'],
        ['time' => '23 Jul 2026, 17:20', 'actor' => 'Sistem', 'role' => 'System', 'module' => 'Auth', 'type' => 'Login', 'activity' => 'Login berhasil dari perangkat baru', 'ip' => '36.78.10.21', 'risk' => 'Sedang'],
        ['time' => '23 Jul 2026, 16:44', 'actor' => 'Andi Pratama', 'role' => 'Owner', 'module' => 'Pengaturan', 'type' => 'Update', 'activity' => 'Mengunggah logo perusahaan baru', 'ip' => '103.22.18.91', 'risk' => 'Rendah'],
        ['time' => '23 Jul 2026, 15:12', 'actor' => 'Unknown', 'role' => '-', 'module' => 'Auth', 'type' => 'Failed login', 'activity' => 'Percobaan login gagal untuk finance@ruangkarya.example', 'ip' => '182.16.42.10', 'risk' => 'Tinggi'],
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Log aktivitas YokPrinting.ID">

        <title>Log Aktivitas - YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="min-h-screen bg-canvas">
            <header class="border-b border-line bg-white/95 px-4 py-4 backdrop-blur-sm sm:px-6 lg:px-8">
                <div class="mx-auto flex w-full max-w-[1280px] items-center justify-between gap-4">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Kembali ke dashboard
                    </a>
                    <a href="{{ route('roles.index') }}" class="hidden text-sm font-semibold text-muted hover:text-ink sm:inline">Peran & akses</a>
                </div>
            </header>

            <div class="mx-auto w-full max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Audit log</span>
                            <span class="rounded-full bg-accent-soft px-2.5 py-1 text-xs font-medium text-accent">Data tiruan</span>
                        </div>
                        <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Log aktivitas</h1>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-muted">Pantau aktivitas login, perubahan data, update permission, dan event keamanan penting.</p>
                    </div>
                    <button type="button" class="inline-flex w-fit items-center rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800" data-testid="export-activity-log-placeholder">
                        Export log
                    </button>
                </div>

                <section class="mb-6 grid gap-4 md:grid-cols-3" aria-label="Ringkasan log aktivitas">
                    <article class="rounded-xl bg-white p-5 border border-line">
                        <p class="text-xs font-semibold text-muted">Total event hari ini</p>
                        <p class="mt-2 text-2xl font-semibold text-ink">42</p>
                        <p class="mt-1 text-xs text-muted">Termasuk login, invoice, dan role.</p>
                    </article>
                    <article class="rounded-xl bg-white p-5 border border-line">
                        <p class="text-xs font-semibold text-muted">Risiko tinggi</p>
                        <p class="mt-2 text-2xl font-semibold text-red-800">1</p>
                        <p class="mt-1 text-xs text-muted">Percobaan login gagal dari IP asing.</p>
                    </article>
                    <article class="rounded-xl bg-white p-5 border border-line">
                        <p class="text-xs font-semibold text-muted">Event permission</p>
                        <p class="mt-2 text-2xl font-semibold text-ink">6</p>
                        <p class="mt-1 text-xs text-muted">Perubahan role dan akses modul.</p>
                    </article>
                </section>

                <section class="rounded-xl bg-white border border-line" aria-labelledby="activity-filter-heading" x-data="{ type: 'all', module: 'all' }">
                    <div class="border-b border-line px-5 py-4 sm:px-6">
                        <h2 id="activity-filter-heading" class="font-semibold text-ink">Filter log aktivitas</h2>
                        <p class="mt-1 text-sm text-muted">Kontrol filter masih visual, disiapkan untuk query backend audit log.</p>
                    </div>

                    <div class="grid gap-4 border-b border-line p-5 sm:grid-cols-2 lg:grid-cols-5 sm:p-6">
                        <label class="block">
                            <span class="text-sm font-medium text-ink">Cari aktor / aktivitas</span>
                            <input class="form-control mt-1.5" placeholder="Andi, invoice, permission..." data-testid="activity-log-search">
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-ink">Tipe aktivitas</span>
                            <select class="form-control mt-1.5" x-model="type" data-testid="activity-type-filter">
                                <option value="all">Semua tipe</option>
                                <option>Login</option>
                                <option>Failed login</option>
                                <option>Create</option>
                                <option>Update</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-ink">Modul</span>
                            <select class="form-control mt-1.5" x-model="module" data-testid="activity-module-filter">
                                <option value="all">Semua modul</option>
                                <option>Auth</option>
                                <option>Invoice</option>
                                <option>Customer</option>
                                <option>Role</option>
                                <option>Pengaturan</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-ink">Dari tanggal</span>
                            <input type="date" class="form-control mt-1.5" value="2026-07-23" data-testid="activity-date-from">
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-ink">Sampai tanggal</span>
                            <input type="date" class="form-control mt-1.5" value="2026-07-24" data-testid="activity-date-to">
                        </label>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-line text-left text-sm">
                            <thead class="bg-canvas text-xs font-semibold text-muted">
                                <tr>
                                    <th scope="col" class="px-5 py-3 sm:px-6">Waktu</th>
                                    <th scope="col" class="px-4 py-3">Aktor</th>
                                    <th scope="col" class="px-4 py-3">Modul</th>
                                    <th scope="col" class="px-4 py-3">Aktivitas</th>
                                    <th scope="col" class="px-4 py-3">IP</th>
                                    <th scope="col" class="px-4 py-3">Risiko</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($logs as $log)
                                    <tr>
                                        <td class="px-5 py-4 text-muted sm:px-6">{{ $log['time'] }}</td>
                                        <td class="px-4 py-4">
                                            <span class="block font-semibold text-ink">{{ $log['actor'] }}</span>
                                            <span class="mt-1 block text-xs text-muted">{{ $log['role'] }}</span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="rounded-full bg-canvas px-2.5 py-1 text-xs font-semibold text-muted">{{ $log['module'] }}</span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="block font-semibold text-ink">{{ $log['type'] }}</span>
                                            <span class="mt-1 block max-w-md text-xs leading-5 text-muted">{{ $log['activity'] }}</span>
                                        </td>
                                        <td class="px-4 py-4 font-mono text-xs text-muted">{{ $log['ip'] }}</td>
                                        <td class="px-4 py-4">
                                            <span class="rounded-full {{ $log['risk'] === 'Tinggi' ? 'bg-red-100 text-red-800' : ($log['risk'] === 'Sedang' ? 'bg-yellow-100 text-yellow-900' : 'bg-green-100 text-green-800') }} px-2.5 py-1 text-xs font-semibold">{{ $log['risk'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </body>
</html>
