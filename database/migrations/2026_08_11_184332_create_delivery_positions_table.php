<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('repartidor_id')->constrained('repartidores')->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamps();

            $table->index('repartidor_id');
            $table->index('owner_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_positions');
    }
};
