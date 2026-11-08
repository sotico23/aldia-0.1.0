<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de ofertas de pedido a repartidores (modelo Uber/Rappi).
 *
 * Cuando un pedido entra al pool, se genera una oferta para cada repartidor
 * online y disponible en su radio. El repartidor puede aceptar o rechazar.
 * Si expira sin respuesta, se ofrece al siguiente.
 *
 * Estados: 'pending' | 'accepted' | 'rejected' | 'expired' | 'cancelled'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_order_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('repartidor_id')->constrained('repartidores')->cascadeOnDelete();

            // Estado de la oferta
            $table->string('status', 20)->default('pending');

            // Distancia al punto de entrega en el momento de la oferta
            $table->decimal('distancia_km', 8, 3)->nullable();

            // Ganancia estimada para el repartidor (costo_envio * comision_pct)
            $table->decimal('ganancia_estimada', 10, 2)->nullable();

            // La oferta expira automáticamente si no hay respuesta
            $table->timestamp('expires_at')->nullable();

            // Respuesta del repartidor
            $table->timestamp('responded_at')->nullable();
            $table->string('reject_reason', 255)->nullable();

            $table->timestamps();

            // Índices
            $table->index(['pedido_id', 'status']);
            $table->index(['repartidor_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_order_offers');
    }
};
