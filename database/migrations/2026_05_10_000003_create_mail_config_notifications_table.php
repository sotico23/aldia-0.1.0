<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_config_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_config_id')->constrained('mail_configs')->onDelete('cascade');
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');

            $table->enum('tipo', ['error_envio', 'fallo_conexion', 'limite_uso']);
            $table->text('mensaje');
            $table->string('destinatario')->nullable();
            $table->boolean('leido')->default(false);

            $table->timestamps();

            $table->index(['mail_config_id', 'created_at']);
            $table->index(['owner_id', 'leido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_config_notifications');
    }
};
