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
        Schema::table('cupon_usos', function (Blueprint $table) {
            $table->foreignId('venta_id')->nullable()->constrained()->nullOnDelete()->after('pedido_id');
        });
    }

    public function down(): void
    {
        Schema::table('cupon_usos', function (Blueprint $table) {
            $table->dropForeign(['venta_id']);
            $table->dropColumn('venta_id');
        });
    }
};
