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
        Schema::table('entregas', function (Blueprint $table) {
            $table->foreignId('grupo_trabajo_id')->nullable()->constrained('grupo_trabajos')->nullOnDelete()->after('venta_id');
        });
    }

    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->dropForeign(['grupo_trabajo_id']);
            $table->dropColumn('grupo_trabajo_id');
        });
    }
};
