<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_provider') && ! Schema::hasColumn('service_provider', 'owner_id')) {
            Schema::table('service_provider', function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->index('owner_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_provider') && Schema::hasColumn('service_provider', 'owner_id')) {
            Schema::table('service_provider', function (Blueprint $table) {
                $table->dropForeign(['owner_id']);
                $table->dropIndex(['owner_id']);
                $table->dropColumn('owner_id');
            });
        }
    }
};
