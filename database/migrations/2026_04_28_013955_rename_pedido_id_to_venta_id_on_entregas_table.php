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
        Schema::table('entregas', function (Blueprint $table) {
            if (Schema::hasColumn('entregas', 'pedido_id')) {
                $table->renameColumn('pedido_id', 'venta_id');
            } elseif (! Schema::hasColumn('entregas', 'venta_id')) {
                $table->unsignedBigInteger('venta_id')->nullable()->after('owner_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            if (Schema::hasColumn('entregas', 'venta_id')) {
                $table->renameColumn('venta_id', 'pedido_id');
            }
        });
    }
};
