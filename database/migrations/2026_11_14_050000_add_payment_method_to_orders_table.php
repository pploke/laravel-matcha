<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('delivery_type');
        });

        // Pesanan lama dibuat sebelum ada pilihan metode pembayaran dan
        // seluruhnya diproses lewat Midtrans.
        DB::table('orders')->whereNull('payment_method')->update([
            'payment_method' => 'MIDTRANS',
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
