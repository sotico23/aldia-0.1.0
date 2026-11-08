<?php

namespace App\Providers;

use App\Support\SystemSettings;
use Illuminate\Support\ServiceProvider;
use Throwable;

class SystemSettingsServiceProvider extends ServiceProvider
{
    /**
     * Load the encrypted settings snapshot before the database connection is
     * resolved so database, mail, Redis and Reverb credentials stored in the
     * settings table can be used by the current request.
     */
    public function register(): void
    {
        if (! SystemSettings::isRuntimeEnabled()) {
            return;
        }

        try {
            SystemSettings::applyToConfig();
        } catch (Throwable) {
            // Never prevent the application from booting because of settings.
        }
    }

    /**
     * Seed the snapshot on the first request after the table is available.
     */
    public function boot(): void
    {
        if (! SystemSettings::isRuntimeEnabled()) {
            return;
        }

        try {
            if (SystemSettings::readSnapshot() === null) {
                SystemSettings::refreshSnapshot();
                SystemSettings::applyToConfig();
            }
        } catch (Throwable) {
            // Ignore missing tables or an unreachable database during boot.
        }
    }
}
