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
        Schema::table('publicacions', function (Blueprint $table) {
            $table->unsignedInteger('hearts_count')->default(0)->after('likes_count');
        });

        Schema::table('comentario_publicacions', function (Blueprint $table) {
            $table->unsignedInteger('hearts_count')->default(0)->after('likes_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publicacions', function (Blueprint $table) {
            $table->dropColumn('hearts_count');
        });

        Schema::table('comentario_publicacions', function (Blueprint $table) {
            $table->dropColumn('hearts_count');
        });
    }
};
