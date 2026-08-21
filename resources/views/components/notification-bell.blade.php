<div
    class="relative"
    x-data="notificationBell"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        class="btn-icon relative border-transparent"
        aria-label="Lihat notifikasi"
        aria-controls="notification-popover"
        :aria-expanded="open"
        @click="toggle()"
    >
        <i class="iconify tabler--bell text-lg"></i>
        <span
            x-show="count > 0"
            x-cloak
            class="absolute -right-1 -top-1 min-w-4 rounded-full bg-red-500 px-1 text-center text-[10px] font-bold leading-4 text-white ring-2 ring-white"
            x-text="count > 99 ? '99+' : count"
        ></span>
    </button>

    <div
        id="notification-popover"
        x-cloak
        x-show="open"
        x-transition.origin.top.right
        @click.outside="open = false"
        class="absolute right-0 top-full z-50 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-line bg-white shadow-xl shadow-ink/10"
    >
        <div class="flex items-start justify-between gap-4 border-b border-line px-4 py-3">
            <div>
                <p class="text-sm font-semibold text-ink">Notifikasi pembayaran</p>
                <p class="mt-0.5 text-xs text-muted" x-text="count ? `${count} invoice perlu perhatian` : 'Tidak ada invoice prioritas'"></p>
            </div>
            <span class="badge badge-danger !px-2 !py-1 !text-[10px]">Jatuh tempo</span>
        </div>

        <div class="max-h-80 overflow-y-auto">
            <div x-show="loading" class="px-4 py-6 text-center text-sm text-muted" role="status">Memuat notifikasi...</div>
            <div x-show="!loading && error" class="px-4 py-6 text-center text-sm text-red-800" role="alert" x-text="error"></div>
            <div x-show="!loading && !error && notifications.length === 0" class="px-4 py-6 text-center text-sm text-muted">Belum ada invoice yang perlu ditindaklanjuti.</div>

            <template x-for="notification in notifications.slice(0, 3)" :key="notification.invoice_number">
                <a
                    :href="`/payments/invoices/${encodeURIComponent(notification.invoice_number)}`"
                    class="block border-b border-line px-4 py-3 hover:bg-surface-low"
                    @click="open = false"
                >
                    <div class="flex items-start justify-between gap-3">
                        <span class="font-mono text-xs font-semibold text-brand-800" x-text="notification.invoice_number"></span>
                        <span
                            class="badge !px-2 !py-1 !text-[10px]"
                            :class="notification.notification_status === 'overdue' ? 'badge-danger' : 'badge-warning'"
                            x-text="notification.notification_label"
                        ></span>
                    </div>
                    <p class="mt-1 text-sm font-semibold text-ink" x-text="notification.customer?.name ?? 'Pelanggan tidak tersedia'"></p>
                    <p class="mt-1 text-xs text-muted" x-text="`${notification.outstanding_amount_formatted} · Jatuh tempo ${dueLabel(notification.due_date)}`"></p>
                </a>
            </template>
        </div>

        <a
            href="{{ route('notifications.due-invoices.index') }}"
            class="block border-t border-line bg-surface-low px-4 py-3 text-center text-sm font-semibold text-brand-800 hover:bg-brand-50"
            @click="open = false"
        >
            Lihat semua invoice jatuh tempo
        </a>
    </div>
</div>
