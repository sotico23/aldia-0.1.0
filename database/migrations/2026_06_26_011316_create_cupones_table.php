<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 50)->unique();
            $table->enum('tipo', ['porcentaje', 'precio_fijo', 'envio_gratis']);
            $table->decimal('valor', 10, 2);
            $table->text('descripcion')->nullable();
            $table->text('plantilla_html')->nullable();
            $table->json('variables_ejemplo')->nullable();
            $table->integer('max_usos')->default(0);
            $table->integer('usos_actuales')->default(0);
            $table->integer('usos_por_cliente')->default(1);
            $table->decimal('compra_minima', 10, 2)->nullable();
            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_fin')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index('codigo');
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupones');
    }
};
