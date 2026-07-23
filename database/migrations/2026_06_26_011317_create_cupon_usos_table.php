<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupon_usos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cupon_id')->constrained('cupones')->cascadeOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->decimal('monto_total', 10, 2);
            $table->decimal('monto_descuento', 10, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index('cupon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupon_usos');
    }
};
