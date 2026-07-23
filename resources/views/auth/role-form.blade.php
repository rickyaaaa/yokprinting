@php
    $roleCode = $roleCode ?? null;
    $rolePresets = [
        'owner' => [
            'name' => 'Owner',
            'code' => 'owner',
            'description' => 'Akses penuh untuk strategi, laporan, pengaturan, dan manajemen user.',
            'status' => 'active',
            'scope' => 'Semua modul',
            'permissions' => ['dashboard', 'invoice', 'customer', 'product', 'payment', 'report', 'setting', 'role'],
        ],
        'finance_admin' => [
            'name' => 'Admin Finance',
            'code' => 'finance_admin',
            'description' => 'Mengelola invoice, pembayaran, export, dan laporan penjualan.',
            'status' => 'active',
            'scope' => 'Finance & laporan',
            'permissions' => ['dashboard', 'invoice', 'payment', 'report'],
        ],
        'sales' => [
            'name' => 'Sales',
            'code' => 'sales',
            'description' => 'Membuat invoice draft, melihat customer, dan memantau status tagihan terkait.',
            'status' => 'active',
            'scope' => 'Customer & invoice',
            'permissions' => ['dashboard', 'invoice', 'customer'],
        ],
    ];

    $role = $rolePresets[$roleCode] ?? [
        'name' => '',
        'code' => '',
        'description' => '',
        'status' => 'active',
        'scope' => '',
        'permissions' => ['dashboard'],
    ];

    $isEdit = $roleCode !== null;

    $permissionGroups = [
        'Operasional' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'description' => 'Melihat ringkasan bisnis dan aktivitas terbaru.'],
            ['key' => 'invoice', 'label' => 'Invoice', 'description' => 'Membuat, mengedit, dan memantau invoice.'],
            ['key' => 'customer', 'label' => 'Customer', 'description' => 'Mengakses data pelanggan dan riwayat transaksi.'],
            ['key' => 'product', 'label' => 'Produk', 'description' => 'Mengelola katalog produk, harga, dan stok.'],
        ],
        'Keuangan' => [
            ['key' => 'payment', 'label' => 'Pembayaran', 'description' => 'Mencatat pembayaran dan memantau outstanding.'],
            ['key' => 'report', 'label' => 'Laporan', 'description' => 'Membuka laporan penjualan dan export data.'],
        ],
        'Administrasi' => [
            ['key' => 'setting', 'label' => 'Pengaturan', 'description' => 'Mengubah profil usaha dan default sistem.'],
            ['key' => 'role', 'label' => 'Role & permission', 'description' => 'Mengelola peran dan hak akses pengguna.'],
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $isEdit ? 'Edit peran' : 'Tambah peran' }} YokPrinting.ID">

        <title>{{ $isEdit ? 'Edit Peran' : 'Tambah Peran' }} - YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="min-h-screen bg-canvas">
            <header class="border-b border-line bg-white/95 px-4 py-4 backdrop-blur-sm sm:px-6 lg:px-8">
                <div class="mx-auto flex w-full max-w-[1180px] items-center justify-between gap-4">
                    <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Kembali ke daftar peran
                    </a>
                    <a href="{{ route('dashboard') }}" class="hidden text-sm font-semibold text-muted hover:text-ink sm:inline">Dashboard</a>
                </div>
            </header>

            <form
                class="mx-auto grid w-full max-w-[1180px] gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:px-8 lg:py-8"
                method="POST"
                action="{{ $isEdit ? '/roles/'.$role['code'] : '/roles' }}"
                x-data="{ saved: false, selected: @js($role['permissions']) }"
                @submit.prevent="saved = true"
                novalidate
            >
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <section class="space-y-6">
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Akses & keamanan</span>
                            <span class="rounded-full bg-accent-soft px-2.5 py-1 text-xs font-medium text-accent">Data tiruan</span>
                        </div>
                        <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">{{ $isEdit ? 'Edit peran' : 'Tambah peran baru' }}</h1>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-muted">Atur identitas role dan permission yang akan digunakan saat backend role-based access control aktif.</p>
                    </div>

                    <div x-show="saved" x-cloak class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900" data-testid="role-form-saved-notice">
                        <p class="font-semibold">Perubahan peran tersimpan sebagai simulasi frontend.</p>
                        <p class="mt-1">Backend penyimpanan role akan disambungkan pada task berikutnya.</p>
                    </div>

                    <section class="rounded-xl bg-white p-5 border border-line sm:p-6" aria-labelledby="role-identity-heading">
                        <h2 id="role-identity-heading" class="font-semibold text-ink">Informasi peran</h2>
                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-medium text-ink">Nama peran</span>
                                <input name="name" value="{{ $role['name'] }}" required class="form-control mt-1.5" placeholder="Contoh: Admin Finance" data-testid="role-name-input">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-ink">Kode peran</span>
                                <input name="code" value="{{ $role['code'] }}" required class="form-control mt-1.5 font-mono" placeholder="admin_finance" data-testid="role-code-input">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-ink">Status</span>
                                <select name="status" class="form-control mt-1.5" data-testid="role-status-select">
                                    <option value="active" @selected($role['status'] === 'active')>Aktif</option>
                                    <option value="limited" @selected($role['status'] === 'limited')>Terbatas</option>
                                    <option value="disabled" @selected($role['status'] === 'disabled')>Nonaktif</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-ink">Scope akses</span>
                                <input name="scope" value="{{ $role['scope'] }}" class="form-control mt-1.5" placeholder="Contoh: Finance & laporan" data-testid="role-scope-input">
                            </label>
                            <label class="block md:col-span-2">
                                <span class="text-sm font-medium text-ink">Deskripsi</span>
                                <textarea name="description" class="form-control mt-1.5 min-h-28" data-testid="role-description-input">{{ $role['description'] }}</textarea>
                            </label>
                        </div>
                    </section>

                    <section class="rounded-xl bg-white p-5 border border-line sm:p-6" aria-labelledby="role-permission-heading">
                        <h2 id="role-permission-heading" class="font-semibold text-ink">Permission modul</h2>
                        <p class="mt-1 text-sm leading-6 text-muted">Pilih modul yang dapat diakses oleh role ini. Checkbox masih berbasis data tiruan.</p>

                        <div class="mt-5 space-y-5">
                            @foreach ($permissionGroups as $group => $permissions)
                                <div>
                                    <h3 class="text-sm font-semibold text-ink">{{ $group }}</h3>
                                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                                        @foreach ($permissions as $permission)
                                            <label class="flex items-start gap-3 rounded-xl border border-line bg-canvas p-3 text-sm hover:bg-brand-50">
                                                <input
                                                    type="checkbox"
                                                    name="permissions[]"
                                                    value="{{ $permission['key'] }}"
                                                    class="mt-1 size-4 rounded border-line text-brand-700 focus:ring-brand-500"
                                                    x-model="selected"
                                                    data-testid="permission-{{ $permission['key'] }}"
                                                >
                                                <span>
                                                    <span class="block font-semibold text-ink">{{ $permission['label'] }}</span>
                                                    <span class="mt-1 block text-xs leading-5 text-muted">{{ $permission['description'] }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </section>

                <aside class="space-y-6">
                    <section class="rounded-xl bg-white p-5 border border-line" aria-labelledby="role-preview-heading">
                        <h2 id="role-preview-heading" class="font-semibold text-ink">Preview peran</h2>
                        <div class="mt-5 rounded-xl bg-canvas p-4">
                            <p class="text-xs font-semibold text-muted">{{ $isEdit ? 'Mode edit' : 'Mode tambah' }}</p>
                            <p class="mt-2 text-lg font-semibold text-ink">{{ $role['name'] ?: 'Peran baru' }}</p>
                            <p class="mt-1 font-mono text-xs text-muted">{{ $role['code'] ?: 'role_code' }}</p>
                            <p class="mt-3 text-sm leading-6 text-muted">{{ $role['description'] ?: 'Deskripsi role akan tampil di sini.' }}</p>
                        </div>
                        <dl class="mt-5 grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <dt class="text-muted">Permission dipilih</dt>
                                <dd class="mt-1 text-lg font-semibold text-ink" x-text="selected.length"></dd>
                            </div>
                            <div>
                                <dt class="text-muted">Status</dt>
                                <dd class="mt-1 font-semibold text-green-800">{{ $role['status'] === 'active' ? 'Aktif' : 'Terbatas' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="rounded-xl border border-accent-soft bg-accent-soft/50 p-5 text-sm text-accent">
                        <h2 class="font-semibold">Catatan workflow</h2>
                        <p class="mt-2 leading-6">Form ini belum menyimpan ke database. Task backend berikutnya akan menangani validasi, persistensi role, dan sinkronisasi permission.</p>
                    </section>

                    <div class="rounded-xl bg-white p-5 border border-line">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800" data-testid="save-role-button">
                            {{ $isEdit ? 'Simpan perubahan peran' : 'Simpan peran baru' }}
                        </button>
                        <a href="{{ route('roles.index') }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                            Batal
                        </a>
                        @if ($isEdit)
                            <a href="{{ route('roles.permissions.edit', $role['code']) }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50 hover:text-brand-800">
                                Atur izin detail
                            </a>
                        @endif
                    </div>
                </aside>
            </form>
        </main>
    </body>
</html>
