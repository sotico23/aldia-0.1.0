<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('public_profile_id')->constrained('public_profiles')->onDelete('cascade');
            $table->foreignId('cliente_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('numero_pedido')->unique();
            $table->enum('estado', ['pendiente', 'confirmado', 'preparando', 'enviado', 'entregado', 'cancelado'])->default('pendiente');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('impuesto', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->text('notas')->nullable();
            $table->string('nombre_cliente')->nullable();
            $table->string('telefono_cliente')->nullable();
            $table->string('direccion_cliente')->nullable();
            $table->string('metodo_pago')->nullable();
            $table->timestamp('fecha_confirmacion')->nullable();
            $table->timestamp('fecha_entrega')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
