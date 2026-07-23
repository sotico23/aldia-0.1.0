<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('uptime_checks') && ! Schema::hasColumn('uptime_checks', 'owner_id')) {
            Schema::table('uptime_checks', function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->index('owner_id');
            });

            DB::statement('UPDATE uptime_checks SET owner_id = (SELECT monitored_sites.owner_id FROM monitored_sites WHERE monitored_sites.id = uptime_checks.monitored_site_id) WHERE EXISTS (SELECT 1 FROM monitored_sites WHERE monitored_sites.id = uptime_checks.monitored_site_id)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('uptime_checks') && Schema::hasColumn('uptime_checks', 'owner_id')) {
            Schema::table('uptime_checks', function (Blueprint $table) {
                $table->dropForeign(['owner_id']);
                $table->dropIndex(['owner_id']);
                $table->dropColumn('owner_id');
            });
        }
    }
};
