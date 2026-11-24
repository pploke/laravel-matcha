<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_courier')->nullable();
            $table->string('shipping_service')->nullable();
            $table->string('shipping_etd')->nullable();
            $table->string('shipping_destination_id')->nullable();
            $table->string('shipping_destination_label')->nullable();
            $table->integer('shipping_weight_grams')->default(0);
            $table->string('tracking_number')->nullable();
            $table->string('shipping_postal_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_courier',
                'shipping_service',
                'shipping_etd',
                'shipping_destination_id',
                'shipping_destination_label',
                'shipping_weight_grams',
                'tracking_number',
                'shipping_postal_code',
            ]);
        });
    }
};
