<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hitos', function (Blueprint $table) {
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->onDelete('cascade')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('hitos', function (Blueprint $table) {
            $table->dropForeign(['proyecto_id']);
            $table->dropColumn('proyecto_id');
        });
    }
};
