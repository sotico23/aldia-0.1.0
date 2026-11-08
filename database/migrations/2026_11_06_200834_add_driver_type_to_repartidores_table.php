<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Modelo de Driver tipo Uber/Rappi.
 *
 * Agrega a la tabla `repartidores`:
 *   - driver_type        → 'employee' (creado desde /empleados) | 'freelancer' (auto-registrado desde app)
 *   - approval_status    → 'approved' | 'pending_approval' | 'rejected'
 *   - is_online          → Switch Online/Offline del repartidor en la app
 *   - comision_pct       → Porcentaje de comisión para freelancers (0-100)
 *   - rejected_reason    → Motivo de rechazo si approval_status = rejected
 *   - last_online_at     → Última vez que se puso en línea
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repartidores', function (Blueprint $table) {
            // Tipo de repartidor: empleado de planta o freelancer registrado desde la app
            $table->string('driver_type', 20)->default('employee')->after('estado');

            // Estado de aprobación del perfil (relevante para freelancers)
            $table->string('approval_status', 30)->default('approved')->after('driver_type');

            // Switch Online/Offline (tipo Uber): el repartidor decide cuándo acepta pedidos
            $table->boolean('is_online')->default(false)->after('approval_status');

            // Porcentaje de comisión sobre el costo de envío (freelancers)
            $table->decimal('comision_pct', 5, 2)->default(80.00)->after('is_online');

            // Motivo de rechazo del perfil freelancer
            $table->text('rejected_reason')->nullable()->after('comision_pct');

            // Auditoría de disponibilidad
            $table->timestamp('last_online_at')->nullable()->after('last_position_at');

            // Índices para consultas de disponibilidad
            $table->index(['driver_type', 'approval_status']);
            $table->index('is_online');
        });

        // Los repartidores existentes (employees) se marcan como aprobados online
        \DB::table('repartidores')->update([
            'driver_type'     => 'employee',
            'approval_status' => 'approved',
            'is_online'       => true, // employees se consideran siempre operativos
        ]);
    }

    public function down(): void
    {
        Schema::table('repartidores', function (Blueprint $table) {
            $table->dropIndex(['driver_type', 'approval_status']);
            $table->dropIndex(['repartidores_is_online_index']);
            $table->dropColumn([
                'driver_type',
                'approval_status',
                'is_online',
                'comision_pct',
                'rejected_reason',
                'last_online_at',
            ]);
        });
    }
};
