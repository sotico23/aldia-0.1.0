<?php
/**
 * Reverb Rate Limiter Feature Tests
 */

namespace Tests\Feature;

use App\Services\ReverbRateLimiter;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ReverbRateLimiterTest extends TestCase
{
    protected ReverbRateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rateLimiter = app(ReverbRateLimiter::class);
        RateLimiter::clear('test-key');
    }

    public function test_configure_sets_up_rate_limits(): void
    {
        $this->rateLimiter->configure();
        
        $this->assertTrue(RateLimiter::has('reverb:notifications'));
        $this->assertTrue(RateLimiter::has('reverb:delivery'));
        $this->assertTrue(RateLimiter::has('reverb:chat'));
        $this->assertTrue(RateLimiter::has('reverb:presence'));
    }

    public function test_check_limit_returns_false_when_under_limit(): void
    {
        $this->rateLimiter->configure();
        RateLimiter::clear('test-user-1');
        
        $result = $this->rateLimiter->checkLimit('test-user-1', 10, 1);
        
        $this->assertFalse($result);
    }

    public function test_check_limit_returns_true_when_over_limit(): void
    {
        $this->rateLimiter->configure();
        RateLimiter::clear('test-user-2');
        
        for ($i = 0; $i < 5; $i++) {
            $this->rateLimiter->increment('test-user-2', 1);
        }
        
        $result = $this->rateLimiter->checkLimit('test-user-2', 3, 1);
        
        $this->assertTrue($result);
    }

    public function test_increment_increases_attempts(): void
    {
        $this->rateLimiter->configure();
        RateLimiter::clear('test-user-3');
        
        $this->rateLimiter->increment('test-user-3', 1);
        $this->rateLimiter->increment('test-user-3', 1);
        
        $remaining = $this->rateLimiter->remaining('test-user-3', 10);
        
        $this->assertEquals(8, $remaining);
    }

    public function test_clear_resets_attempts(): void
    {
        $this->rateLimiter->configure();
        RateLimiter::clear('test-user-4');
        
        $this->rateLimiter->increment('test-user-4', 1);
        $this->rateLimiter->increment('test-user-4', 1);
        
        $this->rateLimiter->clear('test-user-4');
        
        $remaining = $this->rateLimiter->remaining('test-user-4', 10);
        
        $this->assertEquals(10, $remaining);
    }
}