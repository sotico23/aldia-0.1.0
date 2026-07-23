<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->string('commerce_code')->nullable()->change();
            $table->text('api_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        // SQLite limitation - can't easily revert nullable in SQLite
    }
};
