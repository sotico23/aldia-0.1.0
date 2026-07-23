<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacios', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('producto_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vacios', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropColumn('cliente_id');
        });
    }
};
