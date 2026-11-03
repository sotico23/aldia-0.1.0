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
        Schema::table('ventas', function (Blueprint $table) {
            $table->boolean('has_delivery')->default(false)->after('incluye_iva');
            $table->foreignId('delivery_rate_id')
                ->nullable()
                ->after('has_delivery')
                ->constrained('delivery_rates')
                ->nullOnDelete();
            $table->decimal('delivery_cost', 12, 2)->default(0)->after('delivery_rate_id');
            $table->decimal('delivery_distance_km', 8, 2)->nullable()->after('delivery_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_rate_id');
            $table->dropColumn([
                'has_delivery',
                'delivery_cost',
                'delivery_distance_km',
            ]);
        });
    }
};
