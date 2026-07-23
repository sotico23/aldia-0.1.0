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
        Schema::table('evaluaciones', function (Blueprint $table) {
            // Eliminar columnas string antiguas si existen
            if (Schema::hasColumn('evaluaciones', 'empleado')) {
                $table->dropColumn('empleado');
            }
            if (Schema::hasColumn('evaluaciones', 'evaluador')) {
                $table->dropColumn('evaluador');
            }

            // Añadir IDs de relación
            if (! Schema::hasColumn('evaluaciones', 'empleado_id')) {
                $table->foreignId('empleado_id')->nullable()->after('owner_id')->constrained('empleados')->onDelete('cascade');
            }
            if (! Schema::hasColumn('evaluaciones', 'evaluador_id')) {
                $table->foreignId('evaluador_id')->nullable()->after('empleado_id')->constrained('empleados')->onDelete('cascade');
            }

            // Añadir Otros campos faltantes
            if (! Schema::hasColumn('evaluaciones', 'periodo')) {
                $table->string('periodo', 50)->nullable()->after('fecha');
            }
            if (! Schema::hasColumn('evaluaciones', 'estado')) {
                $table->string('estado', 20)->default('pendiente')->after('tipo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluaciones', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
            $table->dropForeign(['evaluador_id']);
            $table->dropColumn(['empleado_id', 'evaluador_id', 'periodo', 'estado']);
            $table->string('empleado')->nullable();
            $table->string('evaluador')->nullable();
        });
    }
};
