<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('field'); // payment_status | estado
            $table->string('from')->nullable();
            $table->string('to')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('gateway')->nullable();
            $table->timestamps();

            $table->index('pedido_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_status_logs');
    }
};
