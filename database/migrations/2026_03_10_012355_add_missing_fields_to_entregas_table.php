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
            $table->unsignedBigInteger('pedido_id')->nullable()->after('id');
            $table->unsignedBigInteger('vehiculo_id')->nullable()->after('direccion');
            $table->unsignedBigInteger('conductor_id')->nullable()->after('vehiculo_id');
            $table->text('notas')->nullable()->after('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->dropColumn(['pedido_id', 'vehiculo_id', 'conductor_id', 'notas']);
        });
    }
};
