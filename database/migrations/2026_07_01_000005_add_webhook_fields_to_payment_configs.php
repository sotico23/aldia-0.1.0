<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->string('mercadopago_webhook_secret')->nullable()->after('mercadopago_active');
            $table->string('paypal_webhook_id')->nullable()->after('paypal_active');
            $table->decimal('commission_rate', 5, 2)->default(0)->after('use_platform_config');
            $table->string('commission_type')->default('percentage')->after('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->dropColumn([
                'mercadopago_webhook_secret',
                'paypal_webhook_id',
                'commission_rate',
                'commission_type',
            ]);
        });
    }
};
