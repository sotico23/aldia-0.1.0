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
        Schema::table('inventarios', function (Blueprint $table) {
            $table->foreignId('almacen_id')->nullable()->after('producto_id')->constrained('almacenes')->onDelete('cascade');
            $table->unique(['producto_id', 'almacen_id']);
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->foreignId('almacen_id')->nullable()->after('cliente_id')->constrained('almacenes')->onDelete('set null');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('almacen_id')->nullable()->after('cliente_id')->constrained('almacenes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            $table->dropUnique(['producto_id', 'almacen_id']);
            $table->dropForeign(['almacen_id']);
            $table->dropColumn('almacen_id');
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->dropForeign(['almacen_id']);
            $table->dropColumn('almacen_id');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['almacen_id']);
            $table->dropColumn('almacen_id');
        });
    }
};
