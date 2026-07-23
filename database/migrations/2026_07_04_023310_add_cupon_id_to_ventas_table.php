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
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('cupon_id')->nullable()->constrained('cupones')->nullOnDelete()->after('monto_descuento');
            $table->decimal('monto_descuento_cupon', 12, 2)->nullable()->after('cupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['cupon_id']);
            $table->dropColumn(['cupon_id', 'monto_descuento_cupon']);
        });
    }
};
