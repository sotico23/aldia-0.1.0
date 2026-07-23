<?php

use App\Models\AutomationExecution;
use App\Models\User;
use App\Notifications\AutomationFailureAlert;
use App\Services\ObservabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

describe('ObservabilityService', function () {
    beforeEach(function () {
        $this->service = app(ObservabilityService::class);
        $this->user = User::factory()->create();
    });

    test('getTodayExecutions returns correct count', function () {
        AutomationExecution::factory()->count(3)->create([
            'owner_id' => $this->user->getOwnerId(),
        ]);

        expect($this->service->getTodayExecutions())->toBe(3);
    });

    test('getFailedExecutions returns only errors today', function () {
        AutomationExecution::factory()->count(2)->create([
            'owner_id' => $this->user->getOwnerId(),
            'status' => 'error',
        ]);

        AutomationExecution::factory()->count(5)->create([
            'owner_id' => $this->user->getOwnerId(),
            'status' => 'success',
        ]);

        expect($this->service->getFailedExecutions())->toBe(2);
    });

    test('getAverageExecutionTime returns average', function () {
        AutomationExecution::factory()->create([
            'owner_id' => $this->user->getOwnerId(),
            'execution_time_ms' => 100,
        ]);

        AutomationExecution::factory()->create([
            'owner_id' => $this->user->getOwnerId(),
            'execution_time_ms' => 200,
        ]);

        expect($this->service->getAverageExecutionTime())->toBe(150.0);
    });

    test('getAverageExecutionTime returns null when no records', function () {
        expect($this->service->getAverageExecutionTime())->toBeNull();
    });

    test('getSuccessRate returns 100 when no executions', function () {
        expect($this->service->getSuccessRate())->toBe(100.0);
    });

    test('getSuccessRate calculates correctly', function () {
        AutomationExecution::factory()->count(3)->create([
            'owner_id' => $this->user->getOwnerId(),
            'status' => 'success',
        ]);

        AutomationExecution::factory()->create([
            'owner_id' => $this->user->getOwnerId(),
            'status' => 'error',
        ]);

        expect($this->service->getSuccessRate())->toBe(75.0);
    });

    test('getHealthStatus returns healthy when no issues', function () {
        $health = $this->service->getHealthStatus();

        expect($health['status'])->toBe('healthy');
        expect($health['metrics'])->toHaveKeys([
            'pending_jobs', 'failed_jobs', 'today_executions',
            'failed_executions', 'average_execution_time_ms',
            'success_rate_percent',
        ]);
        expect($health['issues'])->toBeEmpty();
    });

    test('getHealthStatus detects failed jobs', function () {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test exception',
            'failed_at' => now(),
        ]);

        $health = $this->service->getHealthStatus();

        expect($health['status'])->toBe('unhealthy');
        expect($health['issues'])->not->toBeEmpty();
    });

    test('getLastExecutionByWorkflow returns grouped results', function () {
        AutomationExecution::factory()->create([
            'owner_id' => $this->user->getOwnerId(),
            'workflow' => 'automation',
            'status' => 'success',
        ]);

        AutomationExecution::factory()->create([
            'owner_id' => $this->user->getOwnerId(),
            'workflow' => 'webhook',
            'status' => 'error',
        ]);

        $grouped = $this->service->getLastExecutionByWorkflow();

        expect($grouped)->toHaveKeys(['automation', 'webhook']);
    });

    test('checkConsecutiveFailures detects 3+ failures', function () {
        foreach (range(1, 3) as $i) {
            AutomationExecution::factory()->create([
                'owner_id' => $this->user->getOwnerId(),
                'workflow' => 'automation',
                'status' => 'error',
                'created_at' => now()->subMinutes(4 - $i),
            ]);
        }

        $alerts = $this->service->checkConsecutiveFailures(3);

        expect($alerts)->not->toBeEmpty();
        expect($alerts[0]['workflow'])->toBe('automation');
        expect($alerts[0]['consecutive_failures'])->toBe(3);
    });

    test('checkConsecutiveFailures ignores single failure', function () {
        AutomationExecution::factory()->create([
            'owner_id' => $this->user->getOwnerId(),
            'workflow' => 'automation',
            'status' => 'error',
        ]);

        $alerts = $this->service->checkConsecutiveFailures(3);

        expect($alerts)->toBeEmpty();
    });

    test('getQueueWaitTime returns null when no jobs', function () {
        expect($this->service->getQueueWaitTime())->toBeNull();
    });

    test('getStaleReservedJobs returns zero when no stale jobs', function () {
        expect($this->service->getStaleReservedJobs())->toBe(0);
    });

    test('getStaleReservedJobs detects stale reserved jobs', function () {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 1,
            'reserved_at' => now()->subMinutes(10)->timestamp,
            'available_at' => now()->subMinutes(10)->timestamp,
            'created_at' => now()->subMinutes(10)->timestamp,
        ]);

        expect($this->service->getStaleReservedJobs())->toBe(1);
    });

    test('health includes stale_reserved_jobs metric', function () {
        $health = $this->service->getHealthStatus();

        expect($health['metrics'])->toHaveKey('stale_reserved_jobs');
    });
});

describe('SystemHealthController', function () {
    test('health endpoint requires API key', function () {
        $response = $this->getJson('/api/v1/automation/health');

        expect($response->status())->toBe(401);
    });
});

describe('AutomationHealthCheck command', function () {
    test('command runs successfully', function () {
        Artisan::call('automations:health-check');

        expect(Artisan::output())->toContain('Estado');
    });

    test('command outputs JSON with --format=json', function () {
        Artisan::call('automations:health-check', ['--format' => 'json']);

        $output = Artisan::output();
        $data = json_decode($output, true);

        expect($data)->toHaveKey('status');
        expect($data)->toHaveKey('metrics');
    });
});

describe('AutomationFailureAlert notification', function () {
    test('notification constructs correctly', function () {
        $notification = new AutomationFailureAlert(
            workflow: 'test-workflow',
            consecutiveFailures: 3,
            lastUuid: 'test-uuid-123',
            lastError: 'Connection timeout',
        );

        expect($notification)->not->toBeNull();
    });
});
