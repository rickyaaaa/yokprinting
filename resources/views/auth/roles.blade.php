@php
    $roles = [
        [
            'name' => 'Owner',
            'code' => 'owner',
            'summary' => 'Akses penuh untuk strategi, laporan, pengaturan, dan manajemen user.',
            'users' => 2,
            'status' => 'Aktif',
            'scope' => 'Semua modul',
            'permissions' => ['Dashboard', 'Invoice', 'Pembayaran', 'Laporan', 'Pengaturan', 'Role'],
        ],
        [
            'name' => 'Admin Finance',
            'code' => 'finance_admin',
            'summary' => 'Mengelola invoice, pembayaran, customer, export, dan laporan penjualan.',
            'users' => 4,
            'status' => 'Aktif',
            'scope' => 'Finance & laporan',
            'permissions' => ['Dashboard', 'Invoice', 'Pembayaran', 'Laporan'],
        ],
        [
            'name' => 'Sales',
            'code' => 'sales',
            'summary' => 'Membuat invoice draft, melihat customer, dan memantau status tagihan terkait.',
            'users' => 5,
            'status' => 'Aktif',
            'scope' => 'Customer & invoice',
            'permissions' => ['Dashboard', 'Invoice', 'Customer'],
        ],
        [
            'name' => 'Viewer',
            'code' => 'viewer',
            'summary' => 'Akses baca untuk dashboard dan laporan tanpa izin mengubah data bisnis.',
            'users' => 3,
            'status' => 'Terbatas',
            'scope' => 'Read-only',
            'permissions' => ['Dashboard', 'Laporan'],
        ],
    ];

    $modules = ['Dashboard', 'Invoice', 'Customer', 'Produk', 'Pembayaran', 'Laporan', 'Pengaturan', 'Role'];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Daftar peran dan hak akses YokPrinting.ID">

        <title>Peran & Akses - YokPrinting.ID</title>

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
                        <span class="truncate font-medium text-ink">Peran & akses</span>
                    </div>
                    <div class="ml-auto hidden text-sm text-muted sm:block">Backend permission aktif</div>
                </header>

                <main class="mx-auto w-full max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="badge badge-brand">Akses & keamanan</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Daftar peran</h1>
                            <p class="mt-1 max-w-3xl text-sm leading-6 text-muted">Kelola rancangan role dan cakupan permission sebelum backend role-based access control diaktifkan.</p>
                        </div>
                        <a href="{{ route('roles.create') }}" class="btn btn-primary w-fit" data-testid="create-role-placeholder">
                            Tambah peran
                        </a>
                    </div>

                    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan peran">
                        @foreach ($roles as $role)
                            <article class="card p-5">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 class="font-semibold text-ink">{{ $role['name'] }}</h2>
                                        <p class="mt-1 font-mono text-xs text-muted">{{ $role['code'] }}</p>
                                    </div>
                                    <span class="badge {{ $role['status'] === 'Aktif' ? 'badge-success' : 'badge-warning' }}">{{ $role['status'] }}</span>
                                </div>
                                <p class="mt-4 text-sm leading-6 text-muted">{{ $role['summary'] }}</p>
                                <dl class="mt-5 grid grid-cols-2 gap-3 text-xs">
                                    <div>
                                        <dt class="text-muted">User</dt>
                                        <dd class="mt-1 text-lg font-semibold text-ink">{{ $role['users'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted">Scope</dt>
                                        <dd class="mt-1 font-semibold text-ink">{{ $role['scope'] }}</dd>
                                    </div>
                                </dl>
                                <div class="mt-5 grid gap-2">
                                    <a href="{{ route('roles.edit', $role['code']) }}" class="btn btn-outline w-full">
                                        Edit peran
                                    </a>
                                    <a href="{{ route('roles.permissions.edit', $role['code']) }}" class="inline-flex w-full items-center justify-center rounded-lg bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-800 hover:bg-brand-100">
                                        Atur izin
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </section>

                    <section class="mt-6 card" aria-labelledby="role-table-heading">
                        <div class="border-b border-line px-5 py-4 sm:px-6">
                            <h2 id="role-table-heading" class="font-semibold text-ink">Matrix permission</h2>
                            <p class="mt-1 text-sm text-muted">Centang menunjukkan modul yang bisa diakses oleh role tersebut pada rancangan awal.</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-line text-left text-sm">
                                <thead class="bg-canvas text-xs font-semibold text-muted">
                                    <tr>
                                        <th scope="col" class="px-5 py-3 sm:px-6">Peran</th>
                                        @foreach ($modules as $module)
                                            <th scope="col" class="px-4 py-3 text-center">{{ $module }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($roles as $role)
                                        <tr>
                                            <th scope="row" class="px-5 py-4 font-semibold text-ink sm:px-6">
                                                {{ $role['name'] }}
                                                <span class="block font-mono text-xs font-normal text-muted">{{ $role['code'] }}</span>
                                            </th>
                                            @foreach ($modules as $module)
                                                <td class="px-4 py-4 text-center">
                                                    @if (in_array($module, $role['permissions'], true))
                                                        <span class="mx-auto grid size-7 place-items-center rounded-full bg-brand-100 text-brand-800" aria-label="{{ $role['name'] }} dapat mengakses {{ $module }}">
                                                            <i class="iconify tabler--check text-base"></i>
                                                        </span>
                                                    @else
                                                        <span class="mx-auto block size-7 rounded-full bg-canvas" aria-label="{{ $role['name'] }} belum dapat mengakses {{ $module }}"></span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
