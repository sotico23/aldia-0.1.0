<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('store_profile_id')->constrained('public_profiles')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['buyer_id', 'store_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
