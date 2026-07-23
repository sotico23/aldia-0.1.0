<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_sessions', function (Blueprint $table) {
            $table->foreignId('transaction_id')
                ->nullable()
                ->after('metadata')
                ->constrained('transactions')
                ->nullOnDelete();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
        });
    }
};
