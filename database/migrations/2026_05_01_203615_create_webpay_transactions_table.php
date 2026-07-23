<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webpay_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('token')->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'approved', 'failed'])->default('pending');
            $table->string('buy_order');
            $table->json('transbank_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webpay_transactions');
    }
};
