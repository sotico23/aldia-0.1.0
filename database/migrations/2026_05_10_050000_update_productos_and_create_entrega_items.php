<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Update unidad_medida to match request exactly if needed
            // Currently it is ['unidad', 'kg', 'lt']
            // Change 'lt' to 'litro' and ensure it's correct
            $table->string('unidad_medida')->default('unidad')->change();
            $table->decimal('peso_por_unidad', 10, 2)->nullable()->after('unidad_medida');
        });

        Schema::create('entrega_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('entregas')->onDelete('cascade');
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('cantidad_pedida', 10, 2);
            $table->decimal('cantidad_entregada', 10, 2)->nullable();
            $table->string('unidad_medida');
            $table->decimal('peso_total', 10, 2)->default(0); // Sum of kg
            $table->decimal('volumen_total', 10, 2)->default(0); // Sum of litros
            $table->integer('unidades_totales')->default(0); // Sum of unidades
            $table->foreignId('owner_id')->constrained('users'); // Isolated per tenant
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_items');
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('peso_por_unidad');
        });
    }
};
