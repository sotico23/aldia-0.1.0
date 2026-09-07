<?php
/**
 * Reverb Auth Service Feature Tests
 */

namespace Tests\Feature;

use App\Services\ReverbAuthService;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\User;
use Tests\TestCase;

class ReverbAuthServiceTest extends TestCase
{
    protected ReverbAuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = app(ReverbAuthService::class);
    }

    public function test_validate_token_returns_user_for_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $result = $this->authService->validateToken($token);
        
        $this->assertNotNull($result);
        $this->assertEquals($user->id, $result->id);
    }

    public function test_validate_token_returns_null_for_invalid_token(): void
    {
        $result = $this->authService->validateToken('invalid-token');
        
        $this->assertNull($result);
    }

    public function test_validate_token_returns_null_for_expired_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['*'], now()->subDay())->plainTextToken;

        $result = $this->authService->validateToken($token);
        
        $this->assertNull($result);
    }

    public function test_store_and_get_connections(): void
    {
        $userId = 1;
        $socketId = 'socket-123';

        $this->authService->storeConnection($userId, $socketId);
        
        $connections = $this->authService->getUserConnections($userId);
        
        $this->assertNotEmpty($connections);
        $this->assertEquals($userId, $connections[0]['user_id']);
        $this->assertEquals($socketId, $connections[0]['socket_id']);
    }

    public function test_remove_connection(): void
    {
        $userId = 2;
        $socketId = 'socket-456';

        $this->authService->storeConnection($userId, $socketId);
        $this->authService->removeConnection($userId, $socketId);
        
        $connections = $this->authService->getUserConnections($userId);
        
        $this->assertEmpty($connections);
    }

    public function test_is_user_online(): void
    {
        $userId = 3;
        $socketId = 'socket-789';

        $this->assertFalse($this->authService->isUserOnline($userId));
        
        $this->authService->storeConnection($userId, $socketId);
        
        $this->assertTrue($this->authService->isUserOnline($userId));
    }
}