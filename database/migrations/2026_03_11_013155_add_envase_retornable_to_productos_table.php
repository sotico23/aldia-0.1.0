<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('envase_retornable')->default(false)->after('stock_minimo');
            $table->string('tipo_envase')->nullable()->after('envase_retornable');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['envase_retornable', 'tipo_envase']);
        });
    }
};
