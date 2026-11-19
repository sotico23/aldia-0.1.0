<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->string('account_number', 16)->nullable()->unique();
        });

        DB::table('wallets')->orderBy('id')->chunkById(200, function ($wallets): void {
            foreach ($wallets as $wallet) {
                do {
                    $number = (string) random_int(1000000000000000, 9999999999999999);
                } while (DB::table('wallets')->where('account_number', $number)->exists());
                DB::table('wallets')->where('id', $wallet->id)->update(['account_number' => $number]);
            }
        });
        Schema::table('wallets', function (Blueprint $table): void {
            $table->string('account_number', 16)->nullable(false)->change();
        });
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->enum('category', ['master_recharge', 'sale_commission', 'withdrawal', 'adjustment', 'refund', 'transfer'])->default('adjustment')->change();
        });
        Schema::create('wallet_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sender_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('recipient_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->uuid('request_key');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['sender_wallet_id', 'request_key']);
        });
        Schema::create('wallet_payment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requester_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('payer_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->uuid('request_key');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('note', 255)->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('transfer_id')->nullable()->constrained('wallet_transfers')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['requester_wallet_id', 'request_key']);
            $table->index(['payer_wallet_id', 'status']);
        });
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER wallets_identity_immutable BEFORE UPDATE OF account_number, user_id ON wallets WHEN NEW.account_number IS NOT OLD.account_number OR NEW.user_id IS NOT OLD.user_id BEGIN SELECT RAISE(ABORT, 'Wallet identity is immutable'); END");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER wallets_identity_immutable BEFORE UPDATE ON wallets FOR EACH ROW BEGIN IF NOT (NEW.account_number <=> OLD.account_number) OR NOT (NEW.user_id <=> OLD.user_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Wallet identity is immutable'; END IF; END");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['sqlite', 'mysql'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS wallets_identity_immutable');
        }
        Schema::dropIfExists('wallet_payment_requests');
        Schema::dropIfExists('wallet_transfers');
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropUnique(['account_number']);
            $table->dropColumn('account_number');
        });
    }
};
