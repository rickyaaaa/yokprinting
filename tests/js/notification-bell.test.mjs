import assert from 'node:assert/strict';
import test from 'node:test';

import { createNotificationBell, markDueInvoiceNotificationsRead } from '../../resources/js/notification-bell.js';

const unreadPayload = (count = 2) => ({
    data: Array.from({ length: count }, (_, index) => ({
        invoice_number: `INV-${index + 1}`,
        is_read: false,
    })),
    summary: { unread_count: count, total_count: count },
});

test('opening the notification panel optimistically clears the unread badge and persists once', async () => {
    let markCalls = 0;
    const bell = createNotificationBell({
        loadNotifications: async () => unreadPayload(2),
        markNotificationsRead: async () => {
            markCalls += 1;
            return { data: { unread_count: 0, read_at: '2026-10-16T12:00:00+07:00' } };
        },
    });

    await bell.init();
    assert.equal(bell.count, 2);

    const opening = bell.toggle();
    assert.equal(bell.count, 0);
    await opening;

    assert.equal(markCalls, 1);
    assert.equal(bell.readError, '');
    assert.ok(bell.notifications.every((notification) => notification.is_read));
});

test('double click cannot send two mark-as-read requests', async () => {
    let resolveMark;
    let markCalls = 0;
    const markFinished = new Promise((resolve) => {
        resolveMark = resolve;
    });
    const bell = createNotificationBell({
        loadNotifications: async () => unreadPayload(1),
        markNotificationsRead: async () => {
            markCalls += 1;
            await markFinished;
            return { data: { unread_count: 0 } };
        },
    });

    await bell.init();
    const firstClick = bell.toggle();
    const secondClick = bell.toggle();
    assert.equal(markCalls, 1);

    resolveMark();
    await Promise.all([firstClick, secondClick]);
    assert.equal(markCalls, 1);
});

test('failed persistence restores the previous badge and shows the actual error', async () => {
    const bell = createNotificationBell({
        loadNotifications: async () => unreadPayload(3),
        markNotificationsRead: async () => {
            throw new Error('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        },
    });

    await bell.init();
    await bell.toggle();

    assert.equal(bell.count, 3);
    assert.equal(bell.readError, 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    assert.ok(bell.notifications.every((notification) => !notification.is_read));
});

test('an empty or already-read panel does not send a mutation request', async () => {
    let markCalls = 0;
    const bell = createNotificationBell({
        loadNotifications: async () => ({ data: [], summary: { unread_count: 0, total_count: 0 } }),
        markNotificationsRead: async () => {
            markCalls += 1;
        },
    });

    await bell.init();
    await bell.toggle();

    assert.equal(bell.count, 0);
    assert.equal(markCalls, 0);
});

test('mark-as-read sends same-origin session credentials and CSRF', async () => {
    let request;
    const fetchImpl = async (url, options) => {
        request = { url, options };
        return {
            ok: true,
            status: 200,
            json: async () => ({ data: { unread_count: 0 } }),
        };
    };

    await markDueInvoiceNotificationsRead(fetchImpl, 'csrf-token');

    assert.equal(request.url, '/api/notifications/due-invoices/read');
    assert.equal(request.options.method, 'POST');
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(request.options.headers['X-CSRF-TOKEN'], 'csrf-token');
});
