<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('uptime:check')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('payments:audit --report-only')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('automations:dispatch')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('automations:prune --days=90')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('subscriptions:check')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('subscriptions:renew')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('automations:health-check --format=compact')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->sendOutputTo(storage_path('logs/health-check.log'));

Schedule::command('payments:cleanup --days=7')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::call(function () {
    DB::table('notifications')
        ->where('created_at', '<', now()->subDays(7))
        ->delete();
})->daily()->name('notifications:prune')->withoutOverlapping()->onOneServer();

Schedule::command('call-center:send-recordatorios')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('stock:check-low')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('grupos:cerrar-asignaciones')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('trial:notify')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer();

// ── FASE 3: Backup & Maintenance ────────────────────────────────────────────

Schedule::command('backup:database --compress')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('cleanup:temp-data')
    ->dailyAt('05:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('horizon:snapshot')
    ->dailyAt('06:30')
    ->withoutOverlapping()
    ->onOneServer();
