<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_require_authentication(): void
    {
        $this
            ->getJson(
                '/api/notifications'
            )
            ->assertUnauthorized();

        $this
            ->getJson(
                '/api/notifications/unread-count'
            )
            ->assertUnauthorized();

        $this
            ->patchJson(
                '/api/notifications/read-all'
            )
            ->assertUnauthorized();
    }
}
