<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // 1. Cambiamos 'text' por 'string' con longitud de 500 caracteres
            $table->string('endpoint', 500);

            $table->json('keys_json')->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            // 2. El índice único funcionará sin problemas sobre el tipo string
            $table->unique('endpoint', 'push_subscriptions_endpoint_unique');

            // Nota: foreignId() ya crea un índice implícito para 'user_id', pero mantenerlo explícito no causa error.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
