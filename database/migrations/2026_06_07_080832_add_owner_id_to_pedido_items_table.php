<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pedido_items') && ! Schema::hasColumn('pedido_items', 'owner_id')) {
            Schema::table('pedido_items', function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->index('owner_id');
            });

            DB::statement('UPDATE pedido_items SET owner_id = (SELECT pedidos.owner_id FROM pedidos WHERE pedidos.id = pedido_items.pedido_id) WHERE EXISTS (SELECT 1 FROM pedidos WHERE pedidos.id = pedido_items.pedido_id)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pedido_items') && Schema::hasColumn('pedido_items', 'owner_id')) {
            Schema::table('pedido_items', function (Blueprint $table) {
                $table->dropForeign(['owner_id']);
                $table->dropIndex(['owner_id']);
                $table->dropColumn('owner_id');
            });
        }
    }
};
