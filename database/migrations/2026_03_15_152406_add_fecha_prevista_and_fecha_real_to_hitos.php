<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hitos', function (Blueprint $table) {
            $table->date('fecha_prevista')->nullable()->after('fecha_vencimiento');
            $table->date('fecha_real')->nullable()->after('fecha_prevista');
        });
    }

    public function down(): void
    {
        Schema::table('hitos', function (Blueprint $table) {
            $table->dropColumn(['fecha_prevista', 'fecha_real']);
        });
    }
};
