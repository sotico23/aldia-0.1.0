<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('guard_name');
            $table->unsignedBigInteger('created_by')->nullable()->after('owner_id');

            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['owner_id', 'created_by']);
        });
    }
};
