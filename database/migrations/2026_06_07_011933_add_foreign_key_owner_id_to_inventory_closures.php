<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_closures', function (Blueprint $table) {
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_closures', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });
    }
};
