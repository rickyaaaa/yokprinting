@php
    $currentUser = auth()->user();
    $displayName = $currentUser?->name ?? 'Andi Pratama';
    $displayRole = match ($currentUser?->role) {
        'finance_admin' => 'Admin finance',
        'operations' => 'Operasional',
        'viewer' => 'Viewer',
        default => 'Pemilik usaha',
    };
    $initials = \Illuminate\Support\Str::of($displayName)
        ->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn (string $word) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($word, 0, 1)))
        ->implode('');

    $currentRole = $currentUser?->role === \App\Models\User::ROLE_OWNER
        ? null
        : $currentUser?->roleDefinition()->with('permissions')->first();
    $canViewExpenses = $currentUser?->isActive() && (
        $currentUser->role === \App\Models\User::ROLE_OWNER
        || ($currentRole
            && $currentRole->status !== \App\Models\Role::STATUS_DISABLED
            && $currentRole->permissions->contains('code', 'expense.view'))
    );
    $canViewReports = $currentUser?->isActive() && (
        $currentUser->role === \App\Models\User::ROLE_OWNER
        || ($currentRole
            && $currentRole->status !== \App\Models\Role::STATUS_DISABLED
            && $currentRole->permissions->contains('code', 'report.view'))
    );
    $canViewCashBank = $currentUser?->isActive() && (
        $currentUser->role === \App\Models\User::ROLE_OWNER
        || ($currentRole
            && $currentRole->status !== \App\Models\Role::STATUS_DISABLED
            && $currentRole->permissions->contains('code', 'cash_bank.view'))
    );
    $canViewPurchaseOrders = $currentUser?->isActive() && (
        $currentUser->role === \App\Models\User::ROLE_OWNER
        || ($currentRole
            && $currentRole->status !== \App\Models\Role::STATUS_DISABLED
            && $currentRole->permissions->contains('code', 'purchase_order.view'))
    );
    $canViewGoodsReceipts = $currentUser?->isActive() && (
        $currentUser->role === \App\Models\User::ROLE_OWNER
        || ($currentRole
            && $currentRole->status !== \App\Models\Role::STATUS_DISABLED
            && $currentRole->permissions->contains('code', 'goods_receipt.view'))
    );
    $canViewSupplierPrices = $currentUser?->isActive() && (
        $currentUser->role === \App\Models\User::ROLE_OWNER
        || ($currentRole
            && $currentRole->status !== \App\Models\Role::STATUS_DISABLED
            && $currentRole->permissions->contains('code', 'supplier_price.view'))
    );

    $navGroups = [
        [
            'label' => 'Utama',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => ['dashboard'], 'icon' => 'dashboard'],
                ['label' => 'Buat Invoice', 'route' => 'invoices.create', 'active' => ['invoices.create'], 'icon' => 'invoice-create'],
                ['label' => 'Daftar Invoice', 'route' => 'invoices.index', 'active' => ['invoices.index'], 'icon' => 'invoice-list'],
                ['label' => 'Pembayaran', 'route' => 'payments.receivables.index', 'active' => ['payments.*'], 'icon' => 'payment'],
                ['label' => 'Pengeluaran', 'route' => 'expenses.index', 'active' => ['expenses.*'], 'icon' => 'expenses', 'visible' => $canViewExpenses],
                ['label' => 'Kas & Bank', 'route' => 'cash-bank.index', 'active' => ['cash-bank.*'], 'icon' => 'cash-bank', 'visible' => $canViewCashBank],
                ['label' => 'Peran & akses', 'route' => 'roles.index', 'active' => ['roles.*'], 'icon' => 'roles'],
                ['label' => 'Log aktivitas', 'route' => 'activity-logs.index', 'active' => ['activity-logs.*'], 'icon' => 'logs'],
            ],
        ],
        [
            'label' => 'Data bisnis',
            'items' => [
                ['label' => 'Pelanggan', 'route' => 'customers.index', 'active' => ['customers.*'], 'icon' => 'customers'],
                ['label' => 'Produk', 'route' => 'products.index', 'active' => ['products.*'], 'icon' => 'products'],
                ['label' => 'Purchase Order', 'route' => 'purchase-orders.index', 'active' => ['purchase-orders.*'], 'icon' => 'purchase-order', 'visible' => $canViewPurchaseOrders],
                ['label' => 'Penerimaan Barang', 'route' => 'goods-receipts.index', 'active' => ['goods-receipts.*'], 'icon' => 'goods-receipt', 'visible' => $canViewGoodsReceipts],
                ['label' => 'Harga Supplier', 'route' => 'supplier-prices.index', 'active' => ['supplier-prices.*'], 'icon' => 'supplier-price', 'visible' => $canViewSupplierPrices],
                ['label' => 'Laporan penjualan', 'route' => 'reports.sales.index', 'active' => ['reports.sales.*'], 'icon' => 'reports', 'visible' => $canViewReports],
                ['label' => 'Laba rugi', 'route' => 'reports.profit-loss.index', 'active' => ['reports.profit-loss.*'], 'icon' => 'profit-loss', 'visible' => $canViewReports],
                ['label' => 'Pengaturan', 'route' => 'settings.company-profile.edit', 'active' => ['settings.*'], 'icon' => 'settings'],
            ],
        ],
    ];

    $isItemActive = fn (array $item): bool => collect($item['active'] ?? [])->contains(
        fn (string $pattern): bool => request()->routeIs($pattern),
    );

    $yokPrintingLogo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/yokprinting-logo.png')));
@endphp

<aside
    id="app-sidebar"
    class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-line bg-white text-ink transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:translate-x-0"
    :class="{ 'translate-x-0': sidebarOpen }"
    aria-label="Navigasi utama"
>
    <div class="flex h-16 items-center gap-3 border-b border-line px-5">
        <div class="min-w-0 flex-1">
            <img src="{{ $yokPrintingLogo }}" alt="YokPrinting.ID" class="h-8 w-auto max-w-[170px] object-contain object-left">
            <p class="mt-0.5 text-xs text-muted">Ruang kerja keuangan</p>
        </div>
        <button
            type="button"
            class="ml-auto min-h-11 min-w-11 rounded-full p-2 text-muted hover:bg-surface-low hover:text-ink lg:hidden"
            @click="sidebarOpen = false"
            aria-label="Tutup navigasi"
        >
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/>
            </svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-5">
        @foreach ($navGroups as $group)
            <p @class(['px-3 pb-2 text-xs font-semibold uppercase tracking-[0.16em] text-outline', 'mt-7' => ! $loop->first])>{{ $group['label'] }}</p>
            <div class="space-y-1">
                @foreach ($group['items'] as $item)
                    @continue(($item['visible'] ?? true) === false)
                    @php
                        $active = $isItemActive($item);
                        $href = isset($item['route']) ? route($item['route']) : $item['url'];
                    @endphp
                    <a
                        href="{{ $href }}"
                        @if ($active) aria-current="page" @endif
                        @class([
                            'group flex items-center gap-3 rounded-full px-4 py-2.5 text-sm font-medium',
                            'bg-brand-600 text-white' => $active,
                            'text-muted hover:bg-surface-low hover:text-brand-700' => ! $active,
                        ])
                    >
                        <svg @class(['size-5', 'text-white' => $active, 'text-muted group-hover:text-brand-700' => ! $active]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            @switch($item['icon'])
                                @case('dashboard')
                                    <path d="M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z" stroke-linejoin="round"/>
                                    @break

                                @case('invoice-create')
                                    <path d="M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" stroke-linejoin="round"/>
                                    <path d="M14 3.5v4h4M9 12h6M9 16h4" stroke-linecap="round"/>
                                    @break

                                @case('invoice-list')
                                    <path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5" stroke-linecap="round" stroke-linejoin="round"/>
                                    @break

                                @case('payment')
                                    <path d="M4 7h16v12H4zM7 4h10v3M8 13h8M12 10v6" stroke-linecap="round" stroke-linejoin="round"/>
                                    @break

                                @case('expenses')
                                    <path d="M4 7h16v12H4zM7 4h10v3M8 12h8M8 16h5" stroke-linecap="round" stroke-linejoin="round"/>
                                    @break

                                @case('cash-bank')
                                    <path d="M3 9h18M5 9v9m4-9v9m6-9v9m4-9v9M3 18h18M12 3l9 4H3l9-4Z" stroke-linecap="round" stroke-linejoin="round"/>
                                    @break

                                @case('roles')
                                    <path d="M16 11a4 4 0 1 0-8 0M5 20a7 7 0 0 1 14 0M19 8h2M20 7v2" stroke-linecap="round" stroke-linejoin="round"/>
                                    @break

                                @case('logs')
                                    <path d="M7 4h10M6 8h12M7 12h10M9 16h6M5 20h14" stroke-linecap="round"/>
                                    @break

                                @case('customers')
                                    <path d="M16 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM17 11h4M19 9v4" stroke-linecap="round"/>
                                    @break

                                @case('products')
                                    <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" stroke-linejoin="round"/>
                                    <path d="m4.5 7.8 7.5 4.3 7.5-4.3M12 12.1V21" stroke-linejoin="round"/>
                                    @break

                                @case('purchase-order')
                                    <path d="M3 4h2l1.6 10.2A2 2 0 0 0 8.6 16h8.3a2 2 0 0 0 2-1.7L20 7H6" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2ZM17 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"/>
                                    @break

                                @case('goods-receipt')
                                    <path d="M21 8 12 3 3 8m18 0-9 5m9-5v9l-9 5m0-9L3 8m9 5v9M3 8v9l9 5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="m8 10.5 8-4.5" stroke-linecap="round"/>
                                    @break

                                @case('supplier-price')
                                    <path d="M12 3 4 11v9a1 1 0 0 0 1 1h6v-6h2v6h6a1 1 0 0 0 1-1v-9L12 3Z" stroke-linejoin="round"/>
                                    <path d="M9.5 13.5h5M9.5 13.5c-.9 0-1.5-.55-1.5-1.25S8.6 11 9.5 11h2c.9 0 1.5-.55 1.5-1.25S12.4 8.5 11.5 8.5h-2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M10.5 7.5v1M10.5 13.5v1" stroke-linecap="round"/>
                                    @break

                                @case('reports')
                                    <path d="M5 20V10M12 20V4M19 20v-7" stroke-linecap="round"/>
                                    @break

                                @case('profit-loss')
                                    <path d="M4 19h16M6 16V9m6 7V5m6 11v-4" stroke-linecap="round"/>
                                    <path d="m5 7 5-3 4 3 5-4" stroke-linecap="round" stroke-linejoin="round"/>
                                    @break

                                @case('settings')
                                    <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"/>
                                    <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21a2 2 0 1 1-4 0v-.08A1.7 1.7 0 0 0 9 19.36a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.64 15 1.7 1.7 0 0 0 3.08 14H3a2 2 0 1 1 0-4h.08A1.7 1.7 0 0 0 4.64 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.64 1.7 1.7 0 0 0 10 3.08V3a2 2 0 1 1 4 0v.08A1.7 1.7 0 0 0 15 4.64a1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.36 9c.1.33.36.6.64.82.28.22.63.18 1 .18a2 2 0 1 1 0 4h-.08A1.7 1.7 0 0 0 19.4 15Z" stroke-linecap="round" stroke-linejoin="round"/>
                                    @break
                            @endswitch
                        </svg>
                        <span class="truncate">{{ $item['label'] }}</span>
                        @isset($item['badge'])
                            <span @class([
                                'ml-auto rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-white/20 text-white' => $active,
                                'bg-surface-low text-muted' => ! $active,
                            ])>{{ $item['badge'] }}</span>
                        @endisset
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="relative border-t border-line p-3" x-data="{ accountMenuOpen: false }" @keydown.escape.window="accountMenuOpen = false">
        <div
            x-cloak
            x-show="accountMenuOpen"
            @click.outside="accountMenuOpen = false"
            class="absolute bottom-[calc(100%-0.25rem)] left-3 right-3 rounded-xl border border-line bg-white p-2 shadow-lg"
        >
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                    Keluar dari akun
                </button>
            </form>
        </div>
        <button
            type="button"
            class="flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-left hover:bg-surface-low"
            @click="accountMenuOpen = ! accountMenuOpen"
            :aria-expanded="accountMenuOpen"
            aria-haspopup="menu"
        >
            <span class="grid size-9 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ $initials ?: 'AP' }}</span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium">{{ $displayName }}</span>
                <span class="block truncate text-xs text-muted">{{ $displayRole }}</span>
            </span>
            <svg class="size-4 text-outline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>
</aside>
