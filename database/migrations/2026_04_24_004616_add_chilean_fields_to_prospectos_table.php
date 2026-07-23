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
        Schema::table('prospectos', function (Blueprint $table) {
            $table->foreignId('owner_id')->after('id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('rut')->after('email')->nullable();
            $table->string('giro')->after('rut')->nullable();
            $table->string('cargo')->after('empresa')->nullable();
            $table->string('direccion')->after('cargo')->nullable();
            $table->string('comuna')->after('direccion')->nullable();
            $table->string('region')->after('comuna')->nullable();
            $table->string('prioridad')->after('estado')->default('media'); // baja, media, alta
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospectos', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['owner_id', 'rut', 'giro', 'cargo', 'direccion', 'comuna', 'region', 'prioridad']);
        });
    }
};
