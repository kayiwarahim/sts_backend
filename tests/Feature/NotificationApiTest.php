<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'organization_id' => null,
            'name' => 'Notification Test User',
            'email' => 'notification-test@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs(
            $this->user
        );
    }

    public function test_authenticated_user_can_list_own_notifications(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'channel' => 'system',
            'type' => 'system_test',
            'title' => 'Own Notification',
            'message' => 'Visible to this user.',
            'data' => [
                'event_key' => 'TEST:OWN:1',
            ],
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        $anotherUser = User::create([
            'organization_id' => null,
            'name' => 'Another User',
            'email' => 'another@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        Notification::create([
            'user_id' => $anotherUser->id,
            'channel' => 'system',
            'type' => 'system_test',
            'title' => 'Other Notification',
            'message' => 'Must not be visible.',
            'data' => [
                'event_key' => 'TEST:OTHER:1',
            ],
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        $response =
            $this->getJson(
                '/api/notifications'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonCount(
                1,
                'data.data'
            )
            ->assertJsonPath(
                'data.data.0.title',
                'Own Notification'
            );
    }

    public function test_user_can_get_unread_notification_count(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'channel' => 'system',
            'type' => 'test',
            'title' => 'Unread One',
            'message' => 'Unread',
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'channel' => 'system',
            'type' => 'test',
            'title' => 'Unread Two',
            'message' => 'Unread',
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'channel' => 'system',
            'type' => 'test',
            'title' => 'Already Read',
            'message' => 'Read',
            'sent_at' => now(),
            'read_at' => now(),
            'status' => 'sent',
        ]);

        $response =
            $this->getJson(
                '/api/notifications/unread-count'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.count',
                2
            );
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $notification =
            Notification::create([
                'user_id' => $this->user->id,
                'channel' => 'system',
                'type' => 'test',
                'title' => 'Read Me',
                'message' => 'Mark this as read.',
                'sent_at' => now(),
                'status' => 'sent',
            ]);

        $response =
            $this->patchJson(
                "/api/notifications/{$notification->id}/read"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            );

        $this->assertNotNull(
            $notification
                ->fresh()
                ->read_at
        );
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $anotherUser = User::create([
            'organization_id' => null,
            'name' => 'Other User',
            'email' => 'protected@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $notification =
            Notification::create([
                'user_id' => $anotherUser->id,
                'channel' => 'system',
                'type' => 'test',
                'title' => 'Protected Notification',
                'message' => 'Cannot be modified.',
                'sent_at' => now(),
                'status' => 'sent',
            ]);

        $response =
            $this->patchJson(
                "/api/notifications/{$notification->id}/read"
            );

        $response
            ->assertForbidden();

        $this->assertNull(
            $notification
                ->fresh()
                ->read_at
        );
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        foreach (
            range(1, 3)
            as $number
        ) {
            Notification::create([
                'user_id' => $this->user->id,
                'channel' => 'system',
                'type' => 'test',
                'title' => "Notification {$number}",
                'message' => 'Unread notification.',
                'sent_at' => now(),
                'status' => 'sent',
            ]);
        }

        $response =
            $this->patchJson(
                '/api/notifications/read-all'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            );

        $this->assertEquals(
            0,
            Notification::query()
                ->where(
                    'user_id',
                    $this->user->id
                )
                ->whereNull(
                    'read_at'
                )
                ->count()
        );
    }

}