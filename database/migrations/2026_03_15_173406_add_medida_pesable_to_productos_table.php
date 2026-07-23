<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('medida_pesable')->default(false)->after('envase_retornable');
            $table->enum('tipo_medida', ['unidad', 'kilo', 'litro'])->default('unidad')->after('medida_pesable');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['medida_pesable', 'tipo_medida']);
        });
    }
};
