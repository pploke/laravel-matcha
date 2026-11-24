<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->onDelete('cascade');
            $table->string('provider')->default('midtrans');
            $table->string('midtrans_order_id')->unique();
            $table->string('transaction_id')->nullable()->unique();
            $table->string('transaction_status')->nullable();
            $table->string('fraud_status')->nullable();
            $table->string('payment_type')->nullable();
            $table->bigInteger('gross_amount');
            $table->text('snap_token')->nullable();
            $table->text('redirect_url')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};