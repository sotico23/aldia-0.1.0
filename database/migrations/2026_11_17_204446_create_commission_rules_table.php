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
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            // Comercio/tenant dueño de la regla. Nulo = regla global de la plataforma.
            $table->foreignId('tenant_id')->nullable()->constrained('users')->nullOnDelete();
            // Cajero específico. Nulo = regla general del comercio.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('commission_type', ['percentage', 'fixed'])->default('percentage');
            // Porcentaje (0-100) o monto fijo de la comisión.
            $table->decimal('commission_value', 12, 2)->default(0);
            // Monto mínimo de venta para aplicar la comisión.
            $table->decimal('min_sale_amount', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Regla general (user_id null) + regla específica por cajero (user_id set).
            $table->unique(['tenant_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
