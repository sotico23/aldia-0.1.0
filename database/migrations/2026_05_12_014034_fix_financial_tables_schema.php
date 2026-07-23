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
        // Fix Impuestos
        Schema::table('impuestos', function (Blueprint $table) {
            if (! Schema::hasColumn('impuestos', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->constrained('users')->after('id');
            }
        });

        // Fix Cobranzas
        Schema::table('cobranzas', function (Blueprint $table) {
            if (Schema::hasColumn('cobranzas', 'cliente')) {
                $table->renameColumn('cliente', 'cliente_id');
            }
            if (Schema::hasColumn('cobranzas', 'factura')) {
                $table->renameColumn('factura', 'factura_id');
            }
            if (Schema::hasColumn('cobranzas', 'metodo')) {
                $table->renameColumn('metodo', 'metodo_pago');
            }
            if (! Schema::hasColumn('cobranzas', 'referencia')) {
                $table->string('referencia')->nullable()->after('metodo_pago');
            }
        });

        // Fix Pagos
        Schema::table('pagos', function (Blueprint $table) {
            if (Schema::hasColumn('pagos', 'proveedor')) {
                $table->renameColumn('proveedor', 'proveedor_id');
            }
            if (Schema::hasColumn('pagos', 'factura')) {
                $table->renameColumn('factura', 'factura_id');
            }
            if (Schema::hasColumn('pagos', 'metodo')) {
                $table->renameColumn('metodo', 'metodo_pago');
            }
            if (! Schema::hasColumn('pagos', 'referencia')) {
                $table->string('referencia')->nullable()->after('metodo_pago');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('impuestos', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });

        Schema::table('cobranzas', function (Blueprint $table) {
            $table->renameColumn('cliente_id', 'cliente');
            $table->renameColumn('factura_id', 'factura');
            $table->renameColumn('metodo_pago', 'metodo');
            $table->dropColumn('referencia');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->renameColumn('proveedor_id', 'proveedor');
            $table->renameColumn('factura_id', 'factura');
            $table->renameColumn('metodo_pago', 'metodo');
            $table->dropColumn('referencia');
        });
    }
};
