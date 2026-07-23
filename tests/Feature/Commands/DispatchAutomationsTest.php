<?php

use App\Jobs\RunAutomationJob;
use App\Models\AutomationConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('dispatch command does nothing when no enabled automations', function () {
    Queue::fake();

    Artisan::call('automations:dispatch');

    Queue::assertNothingPushed();
});

test('dispatch command does nothing when enabled automations have future next_run_at', function () {
    Queue::fake();

    $user = User::factory()->create();

    AutomationConfig::create([
        'owner_id' => $user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'next_run_at' => now()->addHour(),
    ]);

    Artisan::call('automations:dispatch');

    Queue::assertNothingPushed();
});

test('dispatch command dispatches job for due automation', function () {
    Queue::fake();

    $user = User::factory()->create();

    $config = AutomationConfig::create([
        'owner_id' => $user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'next_run_at' => now()->subMinute(),
    ]);

    Artisan::call('automations:dispatch');

    Queue::assertPushed(RunAutomationJob::class, function ($job) use ($config, $user) {
        return $job->ownerId === $user->getOwnerId()
            && $job->automationConfigId === $config->id;
    });
});

test('dispatch command dispatches job when next_run_at is null', function () {
    Queue::fake();

    $user = User::factory()->create();

    $config = AutomationConfig::create([
        'owner_id' => $user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'next_run_at' => null,
    ]);

    Artisan::call('automations:dispatch');

    Queue::assertPushed(RunAutomationJob::class, function ($job) use ($config, $user) {
        return $job->ownerId === $user->getOwnerId()
            && $job->automationConfigId === $config->id;
    });
});

test('dispatch command updates last_run_at and next_run_at after dispatch', function () {
    Queue::fake();

    $user = User::factory()->create();

    $config = AutomationConfig::create([
        'owner_id' => $user->getOwnerId(),
        'channel' => 'whatsapp',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'next_run_at' => now()->subMinute(),
    ]);

    Artisan::call('automations:dispatch');

    $config->refresh();

    expect($config->last_run_at)->not->toBeNull();
    expect($config->last_run_status)->toBe('dispatched');
    expect($config->next_run_at)->not->toBeNull();
    expect($config->next_run_at->isFuture())->toBeTrue();
});

test('dispatch command skips disabled automations', function () {
    Queue::fake();

    $user = User::factory()->create();

    AutomationConfig::create([
        'owner_id' => $user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => false,
        'next_run_at' => now()->subMinute(),
    ]);

    Artisan::call('automations:dispatch');

    Queue::assertNothingPushed();
});
