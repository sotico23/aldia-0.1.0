<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'factor_conversion')) {
                $table->decimal('factor_conversion', 15, 3)->default(1)->after('unidad_medida');
            }
            if (! Schema::hasColumn('productos', 'peso_base')) {
                $table->decimal('peso_base', 15, 3)->default(0)->after('factor_conversion');
            }
        });

        Schema::table('entrega_items', function (Blueprint $table) {
            $table->renameColumn('peso_total', 'total_kilogramos');
            $table->renameColumn('volumen_total', 'total_litros');
        });

        Schema::table('entrega_items', function (Blueprint $table) {
            $table->decimal('total_kilogramos', 15, 3)->change();
            $table->decimal('total_litros', 15, 3)->change();
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->decimal('total_kilogramos', 15, 3)->default(0)->after('cantidad');
            $table->decimal('total_litros', 15, 3)->default(0)->after('total_kilogramos');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->dropColumn(['total_kilogramos', 'total_litros']);
        });

        Schema::table('entrega_items', function (Blueprint $table) {
            $table->renameColumn('total_kilogramos', 'peso_total');
            $table->renameColumn('total_litros', 'volumen_total');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['factor_conversion', 'peso_base']);
        });
    }
};
