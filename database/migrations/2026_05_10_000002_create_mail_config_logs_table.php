<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_config_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_config_id')->constrained('mail_configs')->onDelete('cascade');
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');

            $table->enum('status', ['success', 'failed', 'timeout']);
            $table->string('test_email');
            $table->text('error_message')->nullable();
            $table->integer('response_time')->nullable();

            $table->timestamps();

            $table->index(['mail_config_id', 'created_at']);
            $table->index(['owner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_config_logs');
    }
};
