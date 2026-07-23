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
        Schema::table('empleados', function (Blueprint $table) {
            $table->string('rut')->nullable()->unique()->after('apellido');
            $table->date('fecha_nacimiento')->nullable()->after('rut');
            $table->string('nacionalidad')->nullable()->after('fecha_nacimiento');
            $table->string('estado_civil')->nullable()->after('nacionalidad');
            $table->string('comuna')->nullable()->after('direccion');
            $table->string('afp')->nullable()->after('salario');
            $table->string('sistema_salud')->nullable()->after('afp');
            $table->string('isapre_nombre')->nullable()->after('sistema_salud');
            $table->string('tipo_contrato')->nullable()->after('fecha_contratacion');
            $table->decimal('sueldo_liquido_pactado', 12, 2)->nullable()->after('salario');
            $table->string('banco_nombre')->nullable()->after('sueldo_liquido_pactado');
            $table->string('banco_tipo_cuenta')->nullable()->after('banco_nombre');
            $table->string('banco_numero_cuenta')->nullable()->after('banco_tipo_cuenta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn([
                'rut', 'fecha_nacimiento', 'nacionalidad', 'estado_civil',
                'comuna', 'afp', 'sistema_salud', 'isapre_nombre',
                'tipo_contrato', 'sueldo_liquido_pactado', 'banco_nombre',
                'banco_tipo_cuenta', 'banco_numero_cuenta',
            ]);
        });
    }
};
