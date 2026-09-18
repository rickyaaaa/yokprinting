<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')
            ->where('dp_required_percent', 0)
            ->update(['dp_required_percent' => 50]);

        DB::table('invoices')
            ->where('terms', 'Pembayaran awal sebelum produksi. Pelunasan dilakukan sebelum barang dikirim atau diambil.')
            ->update([
                'terms' => 'Minimal DP 50% sebelum produksi. Pelunasan dilakukan sebelum barang dikirim atau diambil.',
            ]);

        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('dp_required_percent', 5, 2)->default(50)->change();
        });
    }

    public function down(): void
    {
        DB::table('invoices')
            ->where('dp_required_percent', 50)
            ->update(['dp_required_percent' => 0]);

        DB::table('invoices')
            ->where('terms', 'Minimal DP 50% sebelum produksi. Pelunasan dilakukan sebelum barang dikirim atau diambil.')
            ->update([
                'terms' => 'Pembayaran awal sebelum produksi. Pelunasan dilakukan sebelum barang dikirim atau diambil.',
            ]);

        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('dp_required_percent', 5, 2)->default(0)->change();
        });
    }
};
