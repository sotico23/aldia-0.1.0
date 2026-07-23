<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly, quarterly
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('gateway')->nullable(); // webpay, paypal, mercadopago
            $table->string('gateway_subscription_id')->nullable();
            $table->string('status')->default('trial'); // trial, active, past_due, cancelled, expired
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('business_id');
            $table->index('status');
            $table->index('gateway_subscription_id');
        });

        Schema::create('subscription_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('action'); // created, renewed, changed, cancelled, expired, payment_failed
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_history');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
