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
            $table->string('delivery_recipient_name')->nullable()->after('delivery_distance_km');
            $table->string('delivery_phone', 30)->nullable()->after('delivery_recipient_name');
            $table->string('delivery_address')->nullable()->after('delivery_phone');
            $table->string('delivery_commune', 120)->nullable()->after('delivery_address');
        });

        Schema::table('entregas', function (Blueprint $table) {
            $table->string('telefono', 30)->nullable()->after('cliente');
            $table->string('comuna', 120)->nullable()->after('direccion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_recipient_name',
                'delivery_phone',
                'delivery_address',
                'delivery_commune',
            ]);
        });

        Schema::table('entregas', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'comuna']);
        });
    }
};
