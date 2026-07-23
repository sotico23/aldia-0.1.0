<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->boolean('use_platform_config')->after('mercadopago_active')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->dropColumn('use_platform_config');
        });
    }
};
