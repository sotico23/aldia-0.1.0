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
        Schema::table('asistencia', function (Blueprint $table) {
            $table->foreignId('empleado_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->dropColumn('empleado');
            $table->decimal('horas_trabajadas', 5, 2)->nullable()->after('hora_salida');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asistencia', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
            $table->dropColumn(['empleado_id', 'horas_trabajadas']);
            $table->string('empleado')->nullable();
        });
    }
};
