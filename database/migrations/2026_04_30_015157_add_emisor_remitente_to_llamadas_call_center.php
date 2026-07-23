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
        Schema::table('llamadas_call_center', function (Blueprint $table) {
            $table->string('numero_emisor')->nullable()->after('tipo');
            $table->string('numero_remitente')->nullable()->after('numero_emisor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('llamadas_call_center', function (Blueprint $table) {
            $table->dropColumn(['numero_emisor', 'numero_remitente']);
        });
    }
};
