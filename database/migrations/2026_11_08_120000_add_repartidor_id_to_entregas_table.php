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
            $table->unsignedBigInteger('repartidor_id')->nullable()
                ->after('conductor_id');
            $table->foreign('repartidor_id')
                ->references('id')
                ->on('repartidores')
                ->nullOnDelete();
            $table->index('repartidor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->dropForeign(['repartidor_id']);
            $table->dropIndex(['repartidor_id']);
            $table->dropColumn(['repartidor_id']);
        });
    }
};
