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
        Schema::table('campanas', function (Blueprint $table) {
            $table->string('canal')->nullable()->after('tipo');
            $table->text('objetivo')->nullable()->after('canal');
            $table->decimal('presupuesto_real', 12, 2)->nullable()->after('presupuesto');
            $table->integer('visitas')->nullable()->after('presupuesto_real');
            $table->integer('leads')->nullable()->after('visitas');
            $table->integer('conversiones')->nullable()->after('leads');
            $table->decimal('roi', 10, 2)->nullable()->after('conversiones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campanas', function (Blueprint $table) {
            //
        });
    }
};
