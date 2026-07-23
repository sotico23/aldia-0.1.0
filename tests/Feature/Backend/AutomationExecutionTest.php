<?php

use App\Models\AutomationExecution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('history page loads with paginated executions', function () {
    AutomationExecution::create([
        'owner_id' => $this->user->getOwnerId(),
        'workflow' => 'automation',
        'status' => 'success',
        'triggered_by' => 'scheduler',
        'executed_at' => now(),
    ]);

    AutomationExecution::create([
        'owner_id' => $this->user->getOwnerId(),
        'workflow' => 'automation',
        'status' => 'error',
        'triggered_by' => 'webhook',
        'executed_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('automation.history'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/AutomationHistory')
        ->has('executions.data', 2)
    );
});

test('history page shows empty state when no executions', function () {
    $response = $this->actingAs($this->user)->get(route('automation.history'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/AutomationHistory')
        ->has('executions.data', 0)
    );
});

test('history page is scoped to owner', function () {
    $otherUser = User::factory()->create();

    AutomationExecution::create([
        'owner_id' => $otherUser->getOwnerId(),
        'workflow' => 'automation',
        'status' => 'success',
        'triggered_by' => 'scheduler',
        'executed_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('automation.history'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Backend/AutomationHistory')
        ->has('executions.data', 0)
    );
});

test('show endpoint returns execution details', function () {
    $execution = AutomationExecution::create([
        'owner_id' => $this->user->getOwnerId(),
        'workflow' => 'automation',
        'status' => 'success',
        'triggered_by' => 'scheduler',
        'payload' => ['test' => true],
        'output' => ['result' => 'ok'],
        'execution_time_ms' => 1500,
        'executed_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('automation.history.show', $execution->id));

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'execution' => [
            'id' => $execution->id,
            'workflow' => 'automation',
            'status' => 'success',
            'triggered_by' => 'scheduler',
        ],
    ]);
});

test('show endpoint returns 404 for other owner execution', function () {
    $otherUser = User::factory()->create();

    $execution = AutomationExecution::create([
        'owner_id' => $otherUser->getOwnerId(),
        'workflow' => 'automation',
        'status' => 'success',
        'triggered_by' => 'scheduler',
        'executed_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('automation.history.show', $execution->id));

    $response->assertNotFound();
});

test('unauthenticated user cannot access history page', function () {
    $response = $this->get(route('automation.history'));

    $response->assertRedirect(route('login'));
});
