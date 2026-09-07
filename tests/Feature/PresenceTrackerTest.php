<?php
/**
 * Presence Tracker Feature Tests
 */

namespace Tests\Feature;

use App\Services\PresenceTracker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PresenceTrackerTest extends TestCase
{
    protected PresenceTracker $presenceTracker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenceTracker = app(PresenceTracker::class);
        Cache::flush();
    }

    public function test_track_join_adds_user_to_channel(): void
    {
        $channel = 'chat.conversation.1';
        $userId = 'user-1';
        $userData = ['name' => 'Test User', 'avatar' => 'avatar.jpg'];

        $this->presenceTracker->trackJoin($channel, $userId, $userData);
        
        $presence = $this->presenceTracker->getPresence($channel);
        
        $this->assertArrayHasKey($userId, $presence);
        $this->assertEquals('Test User', $presence[$userId]['name']);
        $this->assertEquals('avatar.jpg', $presence[$userId]['avatar']);
        $this->assertArrayHasKey('joined_at', $presence[$userId]);
        $this->assertArrayHasKey('last_seen', $presence[$userId]);
    }

    public function test_track_leave_removes_user_from_channel(): void
    {
        $channel = 'chat.conversation.2';
        $userId = 'user-2';

        $this->presenceTracker->trackJoin($channel, $userId, ['name' => 'Test User']);
        $this->presenceTracker->trackLeave($channel, $userId);
        
        $presence = $this->presenceTracker->getPresence($channel);
        
        $this->assertArrayNotHasKey($userId, $presence);
    }

    public function test_update_last_seen_updates_timestamp(): void
    {
        $channel = 'chat.conversation.3';
        $userId = 'user-3';

        $this->presenceTracker->trackJoin($channel, $userId, ['name' => 'Test User']);
        
        // Small delay to ensure timestamp changes
        usleep(10000);
        
        $this->presenceTracker->updateLastSeen($channel, $userId);
        
        $presence = $this->presenceTracker->getPresence($channel);
        
        $this->assertArrayHasKey($userId, $presence);
        $this->assertNotEquals(
            $presence[$userId]['joined_at'],
            $presence[$userId]['last_seen']
        );
    }

    public function test_is_user_present_returns_true_when_user_in_channel(): void
    {
        $channel = 'chat.conversation.4';
        $userId = 'user-4';

        $this->presenceTracker->trackJoin($channel, $userId, ['name' => 'Test User']);
        
        $result = $this->presenceTracker->isUserPresent($channel, $userId);
        
        $this->assertTrue($result);
    }

    public function test_is_user_present_returns_false_when_user_not_in_channel(): void
    {
        $channel = 'chat.conversation.5';
        $userId = 'user-5';

        $result = $this->presenceTracker->isUserPresent($channel, $userId);
        
        $this->assertFalse($result);
    }

    public function test_get_user_presence_returns_presence_across_channels(): void
    {
        $userId = 'user-6';

        $this->presenceTracker->trackJoin('chat.conversation.1', $userId, ['name' => 'Test']);
        $this->presenceTracker->trackJoin('notifications.1', $userId, ['name' => 'Test']);
        
        $presence = $this->presenceTracker->getUserPresence($userId);
        
        $this->assertArrayHasKey('chat', $presence);
        $this->assertArrayHasKey('notifications', $presence);
    }
}