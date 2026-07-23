<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversaciones')) {
            Schema::create('conversaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('public_profile_id')->constrained('public_profiles')->onDelete('cascade');
                $table->foreignId('comprador_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('vendedor_id')->constrained('users')->onDelete('cascade');
                $table->string('titulo')->nullable();
                $table->timestamp('ultimo_mensaje_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mensajes')) {
            Schema::create('mensajes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversacion_id')->constrained('conversaciones')->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->text('contenido');
                $table->boolean('leido')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
        Schema::dropIfExists('conversaciones');
    }
};
