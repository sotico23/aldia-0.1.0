<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->decimal('cantidad', 12, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->integer('cantidad')->change();
        });
    }
};
