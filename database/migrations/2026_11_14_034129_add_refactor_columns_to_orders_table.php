<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number')->nullable()->unique()->after('id');
            $table->bigInteger('subtotal')->default(0)->after('user_id'); // atau sesuaikan posisi kolom
            $table->bigInteger('discount_amount')->default(0);
            $table->string('delivery_type')->comment('antar / ambil')->after('discount_amount');
            $table->string('payment_status')->default('UNPAID');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_number',
                'subtotal',
                'discount_amount',
                'delivery_type',
                'payment_status',
                'paid_at',
                'expires_at',
                'completed_at',
                'cancelled_at',
                'notes',
            ]);
        });
    }
};
