<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamo_cuotas', function (Blueprint $table) {
            $table->foreignId('nomina_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prestamo_cuotas', function (Blueprint $table) {
            $table->dropForeign(['nomina_id']);
            $table->dropColumn('nomina_id');
        });
    }
};