<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('likes_count')->default(0)->after('is_active');
            $table->unsignedBigInteger('rating_total')->default(0)->after('likes_count');
            $table->unsignedBigInteger('rating_count')->default(0)->after('rating_total');
        });
    }

    public function down(): void
    {
        Schema::table('public_profiles', function (Blueprint $table) {
            $table->dropColumn(['likes_count', 'rating_total', 'rating_count']);
        });
    }
};
