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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit'])->default('credit');
            $table->enum('category', ['master_recharge', 'sale_commission', 'withdrawal', 'adjustment', 'refund'])->default('adjustment');
            // El monto siempre se registra en positivo (credit = +amount, debit = -amount sobre el saldo).
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('balance_before', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            // Relación polimórfica hacia la entidad origen (Venta o User/Master).
            $table->string('reference_type', 255)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('wallet_id');
            $table->index('created_at');
            // Permite idempotencia y trazabilidad por venta/comercio.
            $table->index(['reference_type', 'reference_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
