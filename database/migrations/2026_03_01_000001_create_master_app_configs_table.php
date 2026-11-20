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
        Schema::create('master_app_configs', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('Al Día Repartidores');
            $table->string('app_icon_url')->nullable();
            $table->boolean('require_rut')->default(false);
            $table->boolean('require_vehicle')->default(true);
            $table->decimal('default_coverage_radius_km', 5, 2)->default(10.00);
            $table->decimal('default_commission_pct', 5, 2)->default(80.00);
            $table->boolean('require_manual_approval')->default(true);
            $table->string('registration_success_message', 255)->default('Tu solicitud fue enviada exitosamente. Recibirás una notificación al ser aprobada.');
            $table->string('terms_conditions_url', 255)->nullable();
            $table->json('extra_metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_app_configs');
    }
};
