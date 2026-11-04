<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->string('modo', 16)->default('radio')->after('name');
            $table->decimal('lat', 10, 7)->nullable()->change();
            $table->decimal('lng', 10, 7)->nullable()->change();
            $table->decimal('radio_km', 6, 2)->nullable()->default(10)->change();
        });

        Schema::table('delivery_rates', function (Blueprint $table) {
            $table->foreignId('zone_id')
                ->nullable()
                ->after('owner_id')
                ->constrained('zones')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('zones')
            ->whereNull('lat')
            ->orWhereNull('lng')
            ->update([
                'lat' => -33.4489,
                'lng' => -70.6693,
                'radio_km' => 10,
            ]);

        Schema::table('delivery_rates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('modo');
            $table->decimal('lat', 10, 7)->nullable(false)->change();
            $table->decimal('lng', 10, 7)->nullable(false)->change();
            $table->decimal('radio_km', 6, 2)->nullable(false)->default(10)->change();
        });
    }
};
