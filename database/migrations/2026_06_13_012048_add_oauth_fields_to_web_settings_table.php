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
        Schema::table('web_settings', function (Blueprint $table) {
            $table->string('google_client_id')->nullable()->after('maintenance_mode');
            $table->text('google_client_secret')->nullable()->after('google_client_id');
            $table->string('google_redirect_uri')->nullable()->after('google_client_secret');
            $table->string('facebook_client_id')->nullable()->after('google_redirect_uri');
            $table->text('facebook_client_secret')->nullable()->after('facebook_client_id');
            $table->string('facebook_redirect_uri')->nullable()->after('facebook_client_secret');
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn([
                'google_client_id',
                'google_client_secret',
                'google_redirect_uri',
                'facebook_client_id',
                'facebook_client_secret',
                'facebook_redirect_uri',
            ]);
        });
    }
};
