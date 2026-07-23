<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'has_explicit_role')) {
                $table->boolean('has_explicit_role')->default(false)->after('banned_at');
            }
            if (! Schema::hasColumn('users', 'business_cover_path')) {
                $table->string('business_cover_path')->nullable()->after('business_logo_path');
            }
            if (! Schema::hasColumn('users', 'primary_color')) {
                $table->string('primary_color', 7)->nullable()->after('business_cover_path');
            }
            if (! Schema::hasColumn('users', 'secondary_color')) {
                $table->string('secondary_color', 7)->nullable()->after('primary_color');
            }
            if (! Schema::hasColumn('users', 'favicon_path')) {
                $table->string('favicon_path')->nullable()->after('secondary_color');
            }
            if (! Schema::hasColumn('users', 'dashboard_name')) {
                $table->string('dashboard_name')->nullable()->after('favicon_path');
            }
            if (! Schema::hasColumn('users', 'dark_mode_preference')) {
                $table->string('dark_mode_preference', 20)->nullable()->after('dashboard_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'has_explicit_role',
                'business_cover_path',
                'primary_color',
                'secondary_color',
                'favicon_path',
                'dashboard_name',
                'dark_mode_preference',
            ]);
        });
    }
};
