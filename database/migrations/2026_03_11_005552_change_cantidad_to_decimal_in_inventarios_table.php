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
        Schema::table('inventarios', function (Blueprint $table) {
            $table->decimal('cantidad', 12, 3)->default(0)->change();
            $table->decimal('cantidad_minima', 12, 3)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            $table->integer('cantidad')->default(0)->change();
            $table->integer('cantidad_minima')->default(0)->change();
        });
    }
};
