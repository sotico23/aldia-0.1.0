<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->foreignId('public_profile_id')->nullable()->constrained('public_profiles')->onDelete('cascade')->after('user_id');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('public_profile_id')->nullable()->constrained('public_profiles')->onDelete('cascade')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropForeign(['public_profile_id']);
            $table->dropColumn('public_profile_id');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['public_profile_id']);
            $table->dropColumn('public_profile_id');
        });
    }
};
