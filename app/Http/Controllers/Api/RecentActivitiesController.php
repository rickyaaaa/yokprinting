<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecentActivitiesController extends Controller
{
    /**
     * Default list of recent business activities.
     */
    private const ACTIVITIES = [
        [
            'id' => 'act-001',
            'type' => 'invoice',
            'tone' => 'brand',
            'title' => 'Invoice INV-2026-0084 dikirim',
            'description' => 'PT Sinar Nusantara menerima invoice desain brand.',
            'occurred_at' => '2026-07-23T23:05:00+07:00',
        ],
        [
            'id' => 'act-002',
            'type' => 'payment',
            'tone' => 'success',
            'title' => 'Pembayaran diterima',
            'description' => 'CV Lautan Rasa membayar Rp12.750.000 melalui transfer bank.',
            'occurred_at' => '2026-07-23T22:33:00+07:00',
        ],
        [
            'id' => 'act-003',
            'type' => 'reminder',
            'tone' => 'warning',
            'title' => 'Invoice mendekati jatuh tempo',
            'description' => 'INV-2026-0078 perlu ditindaklanjuti dalam 2 hari.',
            'occurred_at' => '2026-07-23T21:10:00+07:00',
        ],
        [
            'id' => 'act-004',
            'type' => 'invoice',
            'tone' => 'muted',
            'title' => 'Draft invoice baru dibuat',
            'description' => 'Paket cetak katalog untuk PT Bumi Lestari masuk sebagai draft.',
            'occurred_at' => '2026-07-23T06:40:00+07:00',
        ],
        [
            'id' => 'act-005',
            'type' => 'payment',
            'tone' => 'success',
            'title' => 'Pembayaran parsial dicatat',
            'description' => 'PT Cakra Media membayar Rp4.250.000 untuk INV-2026-0076.',
            'occurred_at' => '2026-07-22T21:20:00+07:00',
        ],
    ];

    /**
     * Return list of recent business activities with optional type filtering.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $type = $request->query('type', 'all');
        $activities = collect(self::ACTIVITIES);

        if ($type && $type !== 'all') {
            $activities = $activities->where('type', $type)->values();
        } else {
            $activities = $activities->values();
        }

        return response()->json([
            'status' => 'success',
            'data' => $activities,
        ]);
    }
}
