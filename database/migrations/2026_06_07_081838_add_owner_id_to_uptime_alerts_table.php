<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('uptime_alerts') && ! Schema::hasColumn('uptime_alerts', 'owner_id')) {
            Schema::table('uptime_alerts', function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->index('owner_id');
            });

            DB::statement('UPDATE uptime_alerts SET owner_id = (SELECT CASE WHEN users.creator_id IS NOT NULL THEN users.creator_id ELSE users.id END FROM users WHERE users.id = uptime_alerts.user_id) WHERE EXISTS (SELECT 1 FROM users WHERE users.id = uptime_alerts.user_id)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('uptime_alerts') && Schema::hasColumn('uptime_alerts', 'owner_id')) {
            Schema::table('uptime_alerts', function (Blueprint $table) {
                $table->dropForeign(['owner_id']);
                $table->dropIndex(['owner_id']);
                $table->dropColumn('owner_id');
            });
        }
    }
};
