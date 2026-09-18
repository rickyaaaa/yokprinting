<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')
            ->where('terms', 'Minimal DP 50% sebelum produksi. Pelunasan dilakukan sebelum barang dikirim atau diambil.')
            ->update([
                'terms' => 'Pembayaran awal sebelum produksi. Pelunasan dilakukan sebelum barang dikirim atau diambil.',
            ]);
    }

    public function down(): void
    {
        DB::table('invoices')
            ->where('terms', 'Pembayaran awal sebelum produksi. Pelunasan dilakukan sebelum barang dikirim atau diambil.')
            ->update([
                'terms' => 'Minimal DP 50% sebelum produksi. Pelunasan dilakukan sebelum barang dikirim atau diambil.',
            ]);
    }
};
