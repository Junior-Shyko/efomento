<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        app(BroadcastManager::class)->setDefaultDriver('reverb');
        require base_path('routes/channels.php');
    }

    #[Test]
    public function app_notifications_are_stored_and_broadcast(): void
    {
        $notification = new AppNotification('Message', 'info', 'Title');

        $this->assertSame(
            ['database', 'broadcast'],
            $notification->via(User::factory()->make())
        );
    }

    #[Test]
    public function reverb_does_not_allow_every_origin(): void
    {
        $this->assertNotContains(
            '*',
            config('reverb.apps.apps.0.allowed_origins')
        );
    }

    #[Test]
    public function users_can_only_authorize_their_own_notification_channel(): void
    {
        [$user, $otherUser] = User::factory()->count(2)->create();

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-App.Models.User.{$user->id}",
                'socket_id' => '123.456',
            ])
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-App.Models.User.{$otherUser->id}",
                'socket_id' => '123.456',
            ])
            ->assertForbidden();
    }
}
