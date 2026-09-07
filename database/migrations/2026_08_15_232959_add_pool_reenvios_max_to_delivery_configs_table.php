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
        Schema::table('delivery_configs', function (Blueprint $table) {
            $table->unsignedSmallInteger('pool_reenvios_max')->nullable()->default(3)->after('pool_reenvio_min');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_configs', function (Blueprint $table) {
            $table->dropColumn('pool_reenvios_max');
        });
    }
};
