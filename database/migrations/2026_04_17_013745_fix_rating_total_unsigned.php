<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_profiles', function (Blueprint $table) {
            $table->bigInteger('rating_total')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('public_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('rating_total')->default(0)->change();
        });
    }
};
