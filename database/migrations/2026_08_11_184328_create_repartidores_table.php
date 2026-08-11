<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repartidores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('estado', 20)->default('offline');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->decimal('radio_km', 6, 2)->default(10);
            $table->string('telegram_chat_id', 50)->nullable();
            $table->timestamp('last_position_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('owner_id');
            $table->index('estado');
            $table->index('last_position_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repartidores');
    }
};
