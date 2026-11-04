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
        Schema::create('pos_cierre_cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->decimal('total_efectivo', 14, 2)->default(0);
            $table->decimal('total_tarjeta', 14, 2)->default(0);
            $table->decimal('total_transferencia', 14, 2)->default(0);
            $table->decimal('total_otros', 14, 2)->default(0);
            $table->decimal('total_general', 14, 2);
            $table->unsignedInteger('cantidad_ventas')->default(0);
            $table->string('referencia')->nullable();
            $table->string('archivo_origen')->nullable();
            $table->timestamp('importado_at')->useCurrent();
            $table->timestamps();

            $table->unique(['owner_id', 'referencia']);
            $table->index(['owner_id', 'fecha_desde', 'fecha_hasta']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_cierre_cajas');
    }
};
