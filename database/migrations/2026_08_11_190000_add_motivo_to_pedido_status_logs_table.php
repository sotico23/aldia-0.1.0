<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_status_logs', function (Blueprint $table) {
            $table->string('motivo', 255)->nullable()->after('gateway');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_status_logs', function (Blueprint $table) {
            $table->dropColumn('motivo');
        });
    }
};
