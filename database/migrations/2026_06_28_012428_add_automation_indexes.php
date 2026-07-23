<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_configs', function (Blueprint $table) {
            $table->index(['enabled', 'next_run_at'], 'idx_automation_configs_enabled_next_run');
        });

        Schema::table('automation_executions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id')->unique();
            $table->index('status', 'idx_automation_executions_status');
            $table->index('executed_at', 'idx_automation_executions_executed_at');
            $table->index(['owner_id', 'executed_at'], 'idx_automation_executions_owner_executed');
        });
    }

    public function down(): void
    {
        Schema::table('automation_configs', function (Blueprint $table) {
            $table->dropIndex('idx_automation_configs_enabled_next_run');
        });

        Schema::table('automation_executions', function (Blueprint $table) {
            $table->dropColumn('uuid');
            $table->dropIndex('idx_automation_executions_status');
            $table->dropIndex('idx_automation_executions_executed_at');
            $table->dropIndex('idx_automation_executions_owner_executed');
        });
    }
};
