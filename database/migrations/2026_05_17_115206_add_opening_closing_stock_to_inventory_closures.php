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
        Schema::table('inventory_closures', function (Blueprint $table) {
            $table->decimal('opening_stock', 12, 3)->nullable()->after('expected_stock')->comment('Stock al inicio del día');
            $table->decimal('closing_stock', 12, 3)->nullable()->after('opening_stock')->comment('Stock al cierre del día');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_closures', function (Blueprint $table) {
            $table->dropColumn(['opening_stock', 'closing_stock']);
        });
    }
};
