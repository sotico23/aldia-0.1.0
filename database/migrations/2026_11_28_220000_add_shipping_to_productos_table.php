<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('envio_gratis')->default(false);
            $table->string('envio_gratis_comuna', 100)->nullable();
            $table->unsignedInteger('precio_envio')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['envio_gratis', 'envio_gratis_comuna', 'precio_envio']);
        });
    }
};
