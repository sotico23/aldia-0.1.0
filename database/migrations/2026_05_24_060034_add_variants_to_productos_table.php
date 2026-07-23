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
        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('productos')->onDelete('cascade');
            $table->string('talla')->nullable()->after('tipo_envase');
            $table->boolean('tiene_variantes')->default(false)->after('talla');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'talla', 'tiene_variantes']);
        });
    }
};
