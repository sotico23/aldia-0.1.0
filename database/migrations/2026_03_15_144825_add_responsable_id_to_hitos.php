<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hitos', function (Blueprint $table) {
            $table->foreignId('responsable_id')->nullable()->constrained('users')->onDelete('set null')->after('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::table('hitos', function (Blueprint $table) {
            $table->dropForeign(['responsable_id']);
            $table->dropColumn('responsable_id');
        });
    }
};
