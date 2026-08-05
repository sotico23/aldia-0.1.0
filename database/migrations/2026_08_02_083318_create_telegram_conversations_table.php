<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('chat_id', 50);
            $table->string('role', 20);
            $table->text('content');
            $table->timestamps();

            $table->index(['owner_id', 'chat_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_conversations');
    }
};
