<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom pendukung idempotensi checkout dan jejak perubahan stok.
     *
     * stock_deducted_at dan stock_restored_at sengaja nullable: pesanan yang
     * dibuat sebelum fitur ini tidak pernah memotong stok, jadi tidak boleh
     * dikembalikan saat dibatalkan.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('checkout_token', 36)->nullable()->unique()->after('order_number');
            $table->timestamp('stock_deducted_at')->nullable()->after('notes');
            $table->timestamp('stock_restored_at')->nullable()->after('stock_deducted_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'checkout_token',
                'stock_deducted_at',
                'stock_restored_at',
            ]);
        });
    }
};
