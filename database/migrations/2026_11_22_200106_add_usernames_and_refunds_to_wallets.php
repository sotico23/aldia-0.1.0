<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->string('username', 30)->nullable()->unique();
        });
        Schema::table('wallet_transfers', function (Blueprint $table): void {
            $table->string('refund_status', 16)->nullable()->index();
            $table->text('refund_reason')->nullable();
            $table->text('refund_response')->nullable();
            $table->timestamp('refund_requested_at')->nullable();
            $table->timestamp('refund_resolved_at')->nullable();
            $table->foreignId('refund_resolved_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transfers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('refund_resolved_by');
            $table->dropIndex(['refund_status']);
            $table->dropColumn(['refund_status', 'refund_reason', 'refund_response', 'refund_requested_at', 'refund_resolved_at']);
        });
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
