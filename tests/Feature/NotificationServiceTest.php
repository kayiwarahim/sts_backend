<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service =
            app(
                NotificationService::class
            );

        $this->user =
            User::create([
                'organization_id' => null,
                'name' => 'Notification Service User',
                'email' => 'notification-service@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
    }

    public function test_system_notification_is_created(): void
    {
        $notification =
            $this->service
                ->createSystemNotification(
                    $this->user,
                    'system_test',
                    'System Test',
                    'This is a test notification.',
                    [
                        'event_key' =>
                            'SYSTEM_TEST:100',
                    ]
                );

        $this->assertDatabaseHas(
            'notifications',
            [
                'id' =>
                    $notification->id,

                'user_id' =>
                    $this->user->id,

                'channel' =>
                    'system',

                'type' =>
                    'system_test',

                'title' =>
                    'System Test',

                'status' =>
                    'sent',
            ]
        );

        $this->assertNull(
            $notification
                ->read_at
        );

        $this->assertNotNull(
            $notification
                ->sent_at
        );
    }

    public function test_same_event_key_does_not_create_duplicate_notification(): void
    {
        $data = [
            'event_key' =>
                'PAYMENT_SUCCESS:500',
        ];

        $first =
            $this->service
                ->createSystemNotification(
                    $this->user,
                    'payment_successful',
                    'Payment Successful',
                    'Payment received.',
                    $data
                );

        $second =
            $this->service
                ->createSystemNotification(
                    $this->user,
                    'payment_successful',
                    'Payment Successful',
                    'Payment received.',
                    $data
                );

        $this->assertEquals(
            $first->id,
            $second->id
        );

        $this->assertEquals(
            1,
            Notification::query()
                ->where(
                    'user_id',
                    $this->user->id
                )
                ->where(
                    'type',
                    'payment_successful'
                )
                ->count()
        );
    }

    public function test_different_event_keys_create_separate_notifications(): void
    {
        $this->service
            ->createSystemNotification(
                $this->user,
                'payment_successful',
                'Payment Successful',
                'First payment.',
                [
                    'event_key' =>
                        'PAYMENT_SUCCESS:1',
                ]
            );

        $this->service
            ->createSystemNotification(
                $this->user,
                'payment_successful',
                'Payment Successful',
                'Second payment.',
                [
                    'event_key' =>
                        'PAYMENT_SUCCESS:2',
                ]
            );

        $this->assertEquals(
            2,
            Notification::query()
                ->where(
                    'user_id',
                    $this->user->id
                )
                ->where(
                    'type',
                    'payment_successful'
                )
                ->count()
        );
    }

    public function test_notification_data_is_cast_to_array(): void
    {
        $notification =
            $this->service
                ->createSystemNotification(
                    $this->user,
                    'test',
                    'Data Test',
                    'Testing notification metadata.',
                    [
                        'event_key' =>
                            'DATA:1',

                        'payment_id' =>
                            44,

                        'amount' =>
                            1000,
                    ]
                );

        $this->assertIsArray(
            $notification->data
        );

        $this->assertEquals(
            44,
            $notification
                ->data[
                    'payment_id'
                ]
        );

        $this->assertEquals(
            1000,
            $notification
                ->data[
                    'amount'
                ]
        );
    }
}