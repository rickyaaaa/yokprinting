@php
    use App\Models\Permission;
    use App\Models\Role;

    $roleCode = $roleCode ?? Role::CODE_FINANCE_ADMIN;
    $roleModel = Role::query()
        ->with('permissions')
        ->where('code', $roleCode)
        ->first();
    $fallbackRoles = [
        Role::CODE_OWNER => ['name' => 'Owner', 'risk' => 'Tinggi', 'description' => 'Role tertinggi dengan akses pengaturan dan permission.'],
        Role::CODE_FINANCE_ADMIN => ['name' => 'Admin Finance', 'risk' => 'Sedang', 'description' => 'Mengelola invoice, pembayaran, dan laporan finance.'],
        'sales' => ['name' => 'Sales', 'risk' => 'Rendah', 'description' => 'Fokus pada customer dan invoice draft.'],
        Role::CODE_VIEWER => ['name' => 'Viewer', 'risk' => 'Rendah', 'description' => 'Akses baca untuk dashboard dan laporan.'],
    ];
    $fallbackRole = $fallbackRoles[$roleCode] ?? [
        'name' => str($roleCode)->replace('_', ' ')->title()->toString(),
        'risk' => 'Sedang',
        'description' => 'Atur izin akses untuk role ini.',
    ];
    $role = [
        'name' => $roleModel?->name ?? $fallbackRole['name'],
        'code' => $roleModel?->code ?? $roleCode,
        'risk' => $fallbackRole['risk'],
        'description' => $roleModel?->description ?? $fallbackRole['description'],
    ];

    $modules = [
        'dashboard' => ['label' => 'Dashboard', 'description' => 'Ringkasan bisnis dan aktivitas terbaru.'],
        'invoice' => ['label' => 'Invoice', 'description' => 'Draft, preview, kirim, dan PDF invoice.'],
        'customer' => ['label' => 'Customer', 'description' => 'Data pelanggan dan riwayat transaksi.'],
        'product' => ['label' => 'Produk', 'description' => 'Katalog, harga, stok, dan kategori.'],
        'payment' => ['label' => 'Pembayaran', 'description' => 'Piutang, penerimaan, dan status lunas.'],
        'report' => ['label' => 'Laporan', 'description' => 'Analitik penjualan dan export.'],
        'setting' => ['label' => 'Pengaturan', 'description' => 'Profil usaha, tema, dan default invoice.'],
        'role' => ['label' => 'Role', 'description' => 'Peran, user, dan permission.'],
    ];

    $actions = [
        'view' => 'Lihat',
        'create' => 'Tambah',
        'update' => 'Ubah',
        'delete' => 'Hapus',
        'export' => 'Export',
    ];

    $selectedCodes = $roleModel
        ? $roleModel->permissions->pluck('code')->values()
        : collect();
    $selected = $selectedCodes
        ->mapToGroups(function (string $code): array {
            [$module, $action] = array_pad(explode('.', $code, 2), 2, null);

            return $module && $action ? [$module => $action] : [];
        })
        ->map(fn ($actions) => $actions->values()->all())
        ->all();
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Pengaturan izin per peran YokPrinting.ID">

        <title>Izin {{ $role['name'] }} - YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="min-h-screen bg-canvas">
            <header class="border-b border-line bg-white/95 px-4 py-4 backdrop-blur-sm sm:px-6 lg:px-8">
                <div class="mx-auto flex w-full max-w-[1280px] items-center justify-between gap-4">
                    <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Kembali ke daftar peran
                    </a>
                    <a href="{{ route('roles.edit', $role['code']) }}" class="hidden text-sm font-semibold text-muted hover:text-ink sm:inline">Edit info peran</a>
                </div>
            </header>

            <form
                class="mx-auto w-full max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                method="POST"
                action="{{ route('api.roles.permissions.update', $role['code']) }}"
                x-data="rolePermissionsForm(@js($role['code']))"
                @submit.prevent="submit($event)"
                novalidate
            >
                @csrf
                @method('PUT')

                <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Permission detail</span>
                        </div>
                        <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Pengaturan izin {{ $role['name'] }}</h1>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-muted">{{ $role['description'] }} Pilih aksi yang boleh dilakukan di tiap modul.</p>
                    </div>
                    <button type="submit" class="inline-flex w-fit items-center rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-wait disabled:opacity-70" data-testid="save-permissions-button" :disabled="saving">
                        <span x-text="saving ? 'Menyimpan...' : 'Simpan izin'"></span>
                    </button>
                </div>

                <div x-show="saved" x-cloak class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900" data-testid="permission-saved-notice">
                    <p class="font-semibold">Pengaturan izin tersimpan.</p>
                    <p class="mt-1" x-text="savedMessage"></p>
                </div>

                <div x-show="errorMessage" x-cloak class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" data-testid="permission-save-error" role="alert">
                    <p class="font-semibold">Izin belum tersimpan.</p>
                    <p class="mt-1" x-text="errorMessage"></p>
                </div>

                <section class="mb-6 grid gap-4 md:grid-cols-3" aria-label="Ringkasan izin peran">
                    <article class="rounded-xl bg-white p-5 border border-line">
                        <p class="text-xs font-semibold text-muted">Role aktif</p>
                        <h2 class="mt-2 text-lg font-semibold text-ink">{{ $role['name'] }}</h2>
                        <p class="mt-1 font-mono text-xs text-muted">{{ $role['code'] }}</p>
                    </article>
                    <article class="rounded-xl bg-white p-5 border border-line">
                        <p class="text-xs font-semibold text-muted">Risiko perubahan</p>
                        <h2 class="mt-2 text-lg font-semibold {{ $role['risk'] === 'Tinggi' ? 'text-red-800' : ($role['risk'] === 'Sedang' ? 'text-yellow-900' : 'text-green-800') }}">{{ $role['risk'] }}</h2>
                        <p class="mt-1 text-xs text-muted">Periksa ulang sebelum memberi akses hapus/export.</p>
                    </article>
                    <article class="rounded-xl bg-white p-5 border border-line">
                        <p class="text-xs font-semibold text-muted">Status halaman</p>
                        <h2 class="mt-2 text-lg font-semibold text-ink">Backend aktif</h2>
                        <p class="mt-1 text-xs text-muted">Perubahan disimpan ke matrix permission role.</p>
                    </article>
                </section>

                <section class="rounded-xl bg-white border border-line" aria-labelledby="permission-matrix-heading">
                    <div class="border-b border-line px-5 py-4 sm:px-6">
                        <h2 id="permission-matrix-heading" class="font-semibold text-ink">Matrix izin per modul</h2>
                        <p class="mt-1 text-sm text-muted">Kolom aksi dibuat eksplisit agar admin bisa membedakan akses lihat, tambah, ubah, hapus, dan export.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-line text-left text-sm">
                            <thead class="bg-canvas text-xs font-semibold text-muted">
                                <tr>
                                    <th scope="col" class="px-5 py-3 sm:px-6">Modul</th>
                                    @foreach ($actions as $action)
                                        <th scope="col" class="px-4 py-3 text-center">{{ $action }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($modules as $moduleKey => $module)
                                    <tr>
                                        <th scope="row" class="px-5 py-4 sm:px-6">
                                            <span class="block font-semibold text-ink">{{ $module['label'] }}</span>
                                            <span class="mt-1 block max-w-xs text-xs font-normal leading-5 text-muted">{{ $module['description'] }}</span>
                                        </th>
                                        @foreach ($actions as $actionKey => $actionLabel)
                                            @php $permissionKey = "{$moduleKey}.{$actionKey}"; @endphp
                                            <td class="px-4 py-4 text-center">
                                                <label class="inline-flex size-8 items-center justify-center rounded-lg hover:bg-brand-50">
                                                    <input
                                                        type="checkbox"
                                                        name="permissions[]"
                                                        value="{{ $permissionKey }}"
                                                        class="size-4 rounded border-line text-brand-700 focus:ring-brand-500"
                                                        @checked(in_array($actionKey, $selected[$moduleKey] ?? [], true))
                                                        data-testid="permission-{{ $permissionKey }}"
                                                    >
                                                    <span class="sr-only">{{ $actionLabel }} {{ $module['label'] }}</span>
                                                </label>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </form>
        </main>
    </body>
</html>
