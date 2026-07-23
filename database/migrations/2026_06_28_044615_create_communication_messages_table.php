<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // internal, order, marketplace
            $table->foreignId('sender_id')->constrained('users');
            $table->foreignId('receiver_id')->nullable()->constrained('users');
            $table->longText('content');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('sender_id');
            $table->index('communication_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_messages');
    }
};
