<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversaciones', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete()->after('vendedor_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete()->after('store_profile_id');
        });

        Schema::table('mensajes_conversacion', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete()->after('receiver_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete()->after('sender_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });

        Schema::table('mensajes_conversacion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });

        Schema::table('conversaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
