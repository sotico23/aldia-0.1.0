<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->integer('level')->default(3)->after('guard_name');
        });

        // Seed initial levels
        DB::table('roles')->where('name', 'Master')->update(['level' => 0]);
        DB::table('roles')->where('name', 'Super Admin')->update(['level' => 1]);
        DB::table('roles')->where('name', 'Administrador')->update(['level' => 2]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
