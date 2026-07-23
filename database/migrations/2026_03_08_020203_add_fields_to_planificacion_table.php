<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planificacion', function (Blueprint $table) {
            $table->renameColumn('nombre', 'titulo');
            $table->foreignId('proyecto_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ubicacion')->nullable();
            $table->decimal('presupuesto', 12, 2)->nullable();
            $table->string('categoria')->nullable();
            $table->text('objetivo')->nullable();
            $table->integer('asistentes_max')->nullable();
            $table->date('fecha_limite')->nullable();
            $table->boolean('requiere_materiales')->default(false);
            $table->text('materiales')->nullable();
            $table->foreignId('proveedor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contacto_emergencia')->nullable();
            $table->string('telefono_emergencia')->nullable();
            $table->timestamp('fecha_inicio_real')->nullable();
            $table->timestamp('fecha_fin_real')->nullable();
            $table->decimal('presupuesto_real', 12, 2)->nullable();
            $table->text('resultados')->nullable();
            $table->text('lecciones_aprendidas')->nullable();
            $table->json('adjuntos')->nullable();
            $table->json('etiquetas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('planificacion', function (Blueprint $table) {
            $table->renameColumn('titulo', 'nombre');
            $table->dropForeign(['proyecto_id']);
            $table->dropForeign(['responsable_id']);
            $table->dropForeign(['proveedor_id']);
            $table->dropColumn([
                'ubicacion', 'presupuesto', 'categoria', 'objetivo',
                'asistentes_max', 'fecha_limite', 'requiere_materiales',
                'materiales', 'contacto_emergencia', 'telefono_emergencia',
                'fecha_inicio_real', 'fecha_fin_real', 'presupuesto_real',
                'resultados', 'lecciones_aprendidas', 'adjuntos', 'etiquetas',
            ]);
        });
    }
};
