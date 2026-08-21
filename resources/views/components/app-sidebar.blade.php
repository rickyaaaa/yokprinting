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
    $canViewSuppliers = $currentUser?->isActive() && (
        $currentUser->role === \App\Models\User::ROLE_OWNER
        || ($currentRole
            && $currentRole->status !== \App\Models\Role::STATUS_DISABLED
            && $currentRole->permissions->contains('code', 'product.view'))
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
                ['label' => 'Supplier', 'route' => 'suppliers.index', 'active' => ['suppliers.*'], 'icon' => 'supplier', 'visible' => $canViewSuppliers],
                ['label' => 'Purchase Order', 'route' => 'purchase-orders.index', 'active' => ['purchase-orders.*'], 'icon' => 'purchase-order', 'visible' => $canViewPurchaseOrders],
                ['label' => 'Penerimaan Barang', 'route' => 'goods-receipts.index', 'active' => ['goods-receipts.*'], 'icon' => 'goods-receipt', 'visible' => $canViewGoodsReceipts],
                ['label' => 'Harga Supplier', 'route' => 'supplier-prices.index', 'active' => ['supplier-prices.*'], 'icon' => 'supplier-price', 'visible' => $canViewSupplierPrices],
                ['label' => 'Laporan penjualan', 'route' => 'reports.sales.index', 'active' => ['reports.sales.*'], 'icon' => 'reports', 'visible' => $canViewReports],
                ['label' => 'Penjualan per pelanggan', 'route' => 'reports.customer-sales.index', 'active' => ['reports.customer-sales.*'], 'icon' => 'reports', 'visible' => $canViewReports],
                ['label' => 'Laba rugi', 'route' => 'reports.profit-loss.index', 'active' => ['reports.profit-loss.*'], 'icon' => 'profit-loss', 'visible' => $canViewReports],
                ['label' => 'Pengaturan', 'route' => 'settings.company-profile.edit', 'active' => ['settings.*'], 'icon' => 'settings'],
            ],
        ],
    ];

    $isItemActive = fn (array $item): bool => collect($item['active'] ?? [])->contains(
        fn (string $pattern): bool => request()->routeIs($pattern),
    );

    // Xeloro-style icon set (Iconify, Tabler icons) — one entry per semantic key used in $navGroups above.
    $iconMap = [
        'dashboard' => 'tabler--layout-dashboard',
        'invoice-create' => 'tabler--file-plus',
        'invoice-list' => 'tabler--file-invoice',
        'payment' => 'tabler--credit-card',
        'expenses' => 'tabler--receipt-2',
        'cash-bank' => 'tabler--building-bank',
        'roles' => 'tabler--shield-lock',
        'logs' => 'tabler--history',
        'customers' => 'tabler--users',
        'products' => 'tabler--box',
        'supplier' => 'tabler--truck-delivery',
        'purchase-order' => 'tabler--shopping-cart',
        'goods-receipt' => 'tabler--package-import',
        'supplier-price' => 'tabler--tag',
        'reports' => 'tabler--chart-bar',
        'profit-loss' => 'tabler--trending-up',
        'settings' => 'tabler--settings',
    ];

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

    <nav
        class="custom-scroll flex-1 overflow-y-auto px-3 py-5"
        data-testid="app-sidebar-nav"
        x-init="
            $nextTick(() => {
                const activeItem = $el.querySelector('[aria-current=page]');
                if (!activeItem) return;

                const itemTop = activeItem.offsetTop;
                const itemBottom = itemTop + activeItem.offsetHeight;
                const viewTop = $el.scrollTop;
                const viewBottom = viewTop + $el.clientHeight;

                if (itemTop < viewTop || itemBottom > viewBottom) {
                    $el.scrollTop = Math.max(0, itemTop - (($el.clientHeight - activeItem.offsetHeight) / 2));
                }
            });
        "
    >
        @foreach ($navGroups as $group)
            <p @class(['px-3 pb-2 flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.16em] text-outline', 'mt-7' => ! $loop->first])>
                {{ $group['label'] }}
                <span class="h-px w-full bg-line"></span>
            </p>
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
                        <i @class([
                            'iconify text-lg shrink-0',
                            $iconMap[$item['icon']] ?? 'tabler--circle-dot',
                            'text-white' => $active,
                            'text-default-500 group-hover:text-brand-700' => ! $active,
                        ])></i>
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
            <i class="iconify tabler--chevron-down text-base text-outline transition-transform" :class="{ 'rotate-180': accountMenuOpen }"></i>
        </button>
    </div>
</aside>
