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
        Schema::table('cupon_uso_detalle', function (Blueprint $table) {
            $table->decimal('cantidad', 12, 3)->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cupon_uso_detalle', function (Blueprint $table) {
            $table->integer('cantidad')->default(1)->change();
        });
    }
};
