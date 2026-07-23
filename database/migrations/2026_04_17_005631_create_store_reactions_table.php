<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('public_profile_id')->constrained('public_profiles')->cascadeOnDelete();
            $table->tinyInteger('like')->default(0);
            $table->tinyInteger('rating')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'public_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_reactions');
    }
};
