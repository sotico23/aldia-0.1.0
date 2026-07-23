<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('gateway'); // webpay, paypal, mercadopago
            $table->string('gateway_transaction_id')->nullable();
            $table->string('type'); // subscription_payment, customer_payment, refund, chargeback, commission, transfer
            $table->string('status'); // pending, approved, failed, refunded, completed
            $table->string('currency', 10)->default('CLP');
            $table->decimal('amount', 12, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('uuid');
            $table->index('business_id');
            $table->index('gateway');
            $table->index('type');
            $table->index('status');
            $table->index('gateway_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
