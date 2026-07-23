<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tesoreria', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->onDelete('cascade');
            $table->string('categoria')->nullable()->after('referencia');
            $table->string('estado')->default('pendiente')->after('categoria');
        });
    }

    public function down(): void
    {
        Schema::table('tesoreria', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['owner_id', 'categoria', 'estado']);
        });
    }
};
