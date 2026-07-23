<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            // PayPal
            $table->string('paypal_client_id')->nullable();
            $table->text('paypal_client_secret')->nullable();
            $table->enum('paypal_mode', ['sandbox', 'live'])->default('sandbox');
            $table->boolean('paypal_active')->default(false);

            // MercadoPago
            $table->string('mercadopago_public_key')->nullable();
            $table->text('mercadopago_access_token')->nullable();
            $table->enum('mercadopago_mode', ['sandbox', 'live'])->default('sandbox');
            $table->boolean('mercadopago_active')->default(false);

            // Rename general active to webpay_active for clarity if needed,
            // but for now let's just keep the new ones.
        });
    }

    public function down(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->dropColumn([
                'paypal_client_id', 'paypal_client_secret', 'paypal_mode', 'paypal_active',
                'mercadopago_public_key', 'mercadopago_access_token', 'mercadopago_mode', 'mercadopago_active',
            ]);
        });
    }
};
