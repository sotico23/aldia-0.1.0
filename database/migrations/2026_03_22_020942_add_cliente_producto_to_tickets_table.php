<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('cliente_id')->nullable()->after('descripcion');
            $table->unsignedBigInteger('producto_id')->nullable()->after('cliente_id');
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('set null');
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropForeign(['producto_id']);
            $table->dropColumn(['cliente_id', 'producto_id']);
        });
    }
};
