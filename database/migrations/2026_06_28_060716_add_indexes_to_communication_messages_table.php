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
        Schema::table('communication_messages', function (Blueprint $table) {
            $table->index(['communication_conversation_id', 'receiver_id', 'read_at'], 'idx_cm_conversation_receiver_read');
            $table->index(['sender_id'], 'idx_cm_sender');
            $table->index(['type', 'communication_conversation_id'], 'idx_cm_type_conversation');
        });
    }

    public function down(): void
    {
        Schema::table('communication_messages', function (Blueprint $table) {
            $table->dropIndex('idx_cm_conversation_receiver_read');
            $table->dropIndex('idx_cm_sender');
            $table->dropIndex('idx_cm_type_conversation');
        });
    }
};
