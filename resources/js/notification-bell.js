const jsonPayload = async (response) => response.json().catch(() => ({}));

const responseMessage = (status, payload = {}) => {
    if (payload.message) {
        return payload.message;
    }

    return {
        401: 'Sesi login telah berakhir. Silakan login kembali.',
        403: 'Anda tidak memiliki izin untuk mengelola notifikasi.',
        419: 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.',
        422: 'Data notifikasi tidak valid.',
        429: 'Terlalu banyak permintaan. Coba lagi sesaat lagi.',
    }[status] ?? 'Notifikasi belum dapat diperbarui.';
};

export const listDueInvoiceNotifications = async (fetchImpl = globalThis.fetch) => {
    const response = await fetchImpl('/api/notifications/due-invoices?days=7&limit=100&sort=due_date&direction=asc', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
    const payload = await jsonPayload(response);

    if (!response.ok) {
        throw new Error(responseMessage(response.status, payload));
    }

    return payload;
};

export const markDueInvoiceNotificationsRead = async (
    fetchImpl = globalThis.fetch,
    csrfToken = globalThis.document?.querySelector('meta[name="csrf-token"]')?.content ?? '',
) => {
    if (!csrfToken) {
        throw new Error('Token keamanan tidak tersedia. Muat ulang halaman lalu coba lagi.');
    }

    const response = await fetchImpl('/api/notifications/due-invoices/read', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ days: 7 }),
    });
    const payload = await jsonPayload(response);

    if (!response.ok) {
        throw new Error(responseMessage(response.status, payload));
    }

    return payload;
};

export const createNotificationBell = ({
    loadNotifications = listDueInvoiceNotifications,
    markNotificationsRead = markDueInvoiceNotificationsRead,
} = {}) => ({
    open: false,
    loading: true,
    markingRead: false,
    error: '',
    readError: '',
    notifications: [],
    count: 0,

    async init() {
        await this.load();
    },

    async toggle() {
        this.open = !this.open;

        if (this.open && !this.loading) {
            await this.markVisibleAsRead();
        }
    },

    dueLabel(date) {
        return new Date(`${date}T00:00:00`).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
        });
    },

    async load() {
        this.loading = true;
        this.error = '';

        try {
            const payload = await loadNotifications();
            this.notifications = payload.data ?? [];
            this.count = payload.summary?.unread_count ?? 0;

            if (this.open) {
                await this.markVisibleAsRead();
            }
        } catch (error) {
            this.notifications = [];
            this.count = 0;
            this.error = error.message;
        } finally {
            this.loading = false;
        }
    },

    async markVisibleAsRead() {
        if (this.markingRead || this.count === 0 || this.notifications.length === 0) {
            return;
        }

        const previousCount = this.count;
        this.count = 0;
        this.readError = '';
        this.markingRead = true;

        try {
            const payload = await markNotificationsRead();
            const readAt = payload.data?.read_at ?? new Date().toISOString();
            this.notifications = this.notifications.map((notification) => ({
                ...notification,
                is_read: true,
                read_at: readAt,
            }));
        } catch (error) {
            this.count = previousCount;
            this.readError = error.message;
        } finally {
            this.markingRead = false;
        }
    },
});
