<?php

use App\Jobs\RunAutomationJob;
use App\Models\AutomationConfig;
use App\Models\AutomationExecution;
use App\Models\ChannelCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('job is queued with correct parameters', function () {
    Queue::fake();

    RunAutomationJob::dispatch($this->user->id, 1);

    Queue::assertPushed(RunAutomationJob::class, function ($job) {
        return $job->ownerId === $this->user->id && $job->automationConfigId === 1;
    });
});

test('job skips when config is disabled', function () {
    $config = AutomationConfig::create([
        'owner_id' => $this->user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => false,
        'selected_reports' => ['ventas'],
    ]);

    RunAutomationJob::dispatchSync($this->user->getOwnerId(), $config->id);

    expect(AutomationExecution::count())->toBe(0);
});

test('job skips when config not found', function () {
    RunAutomationJob::dispatchSync($this->user->getOwnerId(), 999);

    expect(AutomationExecution::count())->toBe(0);
});

test('job sends to n8n when configured', function () {
    config(['services.n8n.webhook_url' => 'https://n8n.example.com/webhook/automation']);

    Http::fake([
        'n8n.example.com/*' => Http::response(['success' => true]),
    ]);

    $config = AutomationConfig::create([
        'owner_id' => $this->user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'selected_reports' => ['resumen_ejecutivo'],
    ]);

    RunAutomationJob::dispatchSync($this->user->getOwnerId(), $config->id);

    $execution = AutomationExecution::where('owner_id', $this->user->getOwnerId())->first();
    expect($execution)->not->toBeNull();
    expect($execution->status)->toBe('sent_to_n8n');
    expect($execution->triggered_by)->toBe('scheduler');

    $config->refresh();
    expect($config->last_run_status)->toBe('sent_to_n8n');
});

test('job creates execution with error when n8n fails', function () {
    Http::fake([
        'n8n.example.com/*' => Http::response(['error' => 'fail'], 500),
    ]);

    $config = AutomationConfig::create([
        'owner_id' => $this->user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'selected_reports' => ['ventas'],
    ]);

    config(['services.n8n.webhook_url' => 'https://n8n.example.com/webhook/automation']);

    RunAutomationJob::dispatchSync($this->user->getOwnerId(), $config->id);

    $execution = AutomationExecution::where('owner_id', $this->user->getOwnerId())->first();
    expect($execution->status)->toBe('error');
});

test('job sends directly when n8n is not configured', function () {
    config(['services.n8n.webhook_url' => null]);

    $config = AutomationConfig::create([
        'owner_id' => $this->user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'selected_reports' => ['ventas'],
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 123]]),
    ]);

    config(['services.telegram.default_chat_id' => '123456']);

    RunAutomationJob::dispatchSync($this->user->getOwnerId(), $config->id);

    $execution = AutomationExecution::where('owner_id', $this->user->getOwnerId())->first();
    expect($execution)->not->toBeNull();
    expect($execution->status)->toBe('success');
});

test('format reports includes all selected sections', function () {
    $config = AutomationConfig::create([
        'owner_id' => $this->user->getOwnerId(),
        'channel' => 'telegram',
        'frequency' => 'daily',
        'execution_time' => '08:00',
        'enabled' => true,
        'selected_reports' => ['resumen_ejecutivo', 'ventas'],
    ]);

    ChannelCredential::create([
        'owner_id' => $this->user->getOwnerId(),
        'telegram_bot_token' => 'test:token',
    ]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);

    config(['services.telegram.default_chat_id' => '123456']);
    config(['services.n8n.webhook_url' => null]);

    RunAutomationJob::dispatchSync($this->user->getOwnerId(), $config->id);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $text = $body['text'] ?? '';

        return str_contains($text, 'Resumen Ejecutivo')
            && str_contains($text, 'Ventas del Mes');
    });
});
