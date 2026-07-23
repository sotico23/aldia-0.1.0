<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mensajes_conversacion', function (Blueprint $table) {
            $table->renameColumn('user_id', 'sender_id');
            $table->foreignId('receiver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('file_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mensajes_conversacion', function (Blueprint $table) {
            $table->dropForeign(['receiver_id']);
            $table->dropColumn(['receiver_id', 'file_path']);
            $table->renameColumn('sender_id', 'user_id');
        });
    }
};
