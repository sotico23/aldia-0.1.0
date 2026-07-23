<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('raffle_prizes') && ! Schema::hasColumn('raffle_prizes', 'owner_id')) {
            Schema::table('raffle_prizes', function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->index('owner_id');
            });

            DB::statement('UPDATE raffle_prizes SET owner_id = (SELECT raffles.owner_id FROM raffles WHERE raffles.id = raffle_prizes.raffle_id) WHERE EXISTS (SELECT 1 FROM raffles WHERE raffles.id = raffle_prizes.raffle_id)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('raffle_prizes') && Schema::hasColumn('raffle_prizes', 'owner_id')) {
            Schema::table('raffle_prizes', function (Blueprint $table) {
                $table->dropForeign(['owner_id']);
                $table->dropIndex(['owner_id']);
                $table->dropColumn('owner_id');
            });
        }
    }
};
