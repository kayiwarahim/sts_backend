<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected User $superAdmin;

    protected User $landlord;

    protected User $tenantUser;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Seed the real application data
        |--------------------------------------------------------------------------
        |
        | RefreshDatabase gives every test a clean testing database. Calling
        | seed() here loads the application's normal DatabaseSeeder, so these
        | authorization tests use the same roles, permissions, users and tenant
        | records as the application instead of creating duplicate test users.
        |
        */

        $this->seed();

        /*
        |--------------------------------------------------------------------------
        | Use seeded users
        |--------------------------------------------------------------------------
        */

        $this->superAdmin = User::role('Super Admin')
            ->firstOrFail();

        $this->landlord = User::role('Landlord')
            ->firstOrFail();

        $this->tenantUser = User::role('Tenant')
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Use the seeded tenant profile
        |--------------------------------------------------------------------------
        */

        $this->tenant = Tenant::query()
            ->where('user_id', $this->tenantUser->id)
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Use the seeded tenant organization
        |--------------------------------------------------------------------------
        */

        $this->organization = Organization::query()
            ->findOrFail($this->tenantUser->organization_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_protected_api_requires_authentication(): void
    {
        $response =
            $this->getJson(
                '/api/properties'
            );

        $response
            ->assertUnauthorized();
    }

    public function test_tenant_portal_requires_authentication(): void
    {
        $response =
            $this->getJson(
                '/api/tenant/me/dashboard'
            );

        $response
            ->assertUnauthorized();
    }

    public function test_landlord_portal_requires_authentication(): void
    {
        $response =
            $this->getJson(
                '/api/landlord/dashboard/summary'
            );

        $response
            ->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Tenant access
    |--------------------------------------------------------------------------
    */

    public function test_tenant_can_access_tenant_dashboard(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->tenantUser,
                    'web'
                )
                ->getJson(
                    '/api/tenant/me/dashboard'
                );

        $this->assertNotEquals(
            401,
            $response->status()
        );

        $this->assertNotEquals(
            403,
            $response->status()
        );
    }

    public function test_tenant_can_access_own_payments_endpoint(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->tenantUser,
                    'web'
                )
                ->getJson(
                    '/api/tenant/me/payments'
                );

        $this->assertNotEquals(
            401,
            $response->status()
        );

        $this->assertNotEquals(
            403,
            $response->status()
        );
    }

    public function test_tenant_cannot_access_landlord_dashboard(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->tenantUser,
                    'web'
                )
                ->getJson(
                    '/api/landlord/dashboard/summary'
                );

        $response
            ->assertForbidden();
    }

    public function test_tenant_cannot_access_management_properties(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->tenantUser,
                    'web'
                )
                ->getJson(
                    '/api/properties'
                );

        $response
            ->assertForbidden();
    }

    public function test_tenant_cannot_access_management_meters(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->tenantUser,
                    'web'
                )
                ->getJson(
                    '/api/meters'
                );

        $response
            ->assertForbidden();
    }

    public function test_tenant_cannot_access_admin_organizations(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->tenantUser,
                    'web'
                )
                ->getJson(
                    '/api/admin/organizations'
                );

        $response
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Landlord access
    |--------------------------------------------------------------------------
    */

    public function test_landlord_can_access_landlord_dashboard(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->landlord,
                    'web'
                )
                ->getJson(
                    '/api/landlord/dashboard'
                );

        $this->assertNotEquals(
            401,
            $response->status()
        );

        $this->assertNotEquals(
            403,
            $response->status()
        );
    }

    public function test_landlord_can_access_properties(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->landlord,
                    'web'
                )
                ->getJson(
                    '/api/properties'
                );

        $this->assertNotEquals(
            401,
            $response->status()
        );

        $this->assertNotEquals(
            403,
            $response->status()
        );
    }

    public function test_landlord_can_access_meters(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->landlord,
                    'web'
                )
                ->getJson(
                    '/api/meters'
                );

        $this->assertNotEquals(
            401,
            $response->status()
        );

        $this->assertNotEquals(
            403,
            $response->status()
        );
    }

    public function test_landlord_cannot_access_admin_organizations(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->landlord,
                    'web'
                )
                ->getJson(
                    '/api/admin/organizations'
                );

        $response
            ->assertForbidden();
    }

    public function test_landlord_cannot_access_tenant_portal(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->landlord,
                    'web'
                )
                ->getJson(
                    '/api/tenant/me/dashboard'
                );

        $response
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Super Admin access
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_access_admin_organizations(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->superAdmin,
                    'web'
                )
                ->getJson(
                    '/api/admin/organizations'
                );

        $this->assertNotEquals(
            401,
            $response->status()
        );

        $this->assertNotEquals(
            403,
            $response->status()
        );
    }

    public function test_super_admin_can_access_properties(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->superAdmin,
                    'web'
                )
                ->getJson(
                    '/api/properties'
                );

        $this->assertNotEquals(
            401,
            $response->status()
        );

        $this->assertNotEquals(
            403,
            $response->status()
        );
    }

    public function test_super_admin_can_access_meters(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->superAdmin,
                    'web'
                )
                ->getJson(
                    '/api/meters'
                );

        $this->assertNotEquals(
            401,
            $response->status()
        );

        $this->assertNotEquals(
            403,
            $response->status()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Identity endpoint
    |--------------------------------------------------------------------------
    */

    public function test_authenticated_users_can_access_me_endpoint(): void
    {
        foreach ([
            $this->superAdmin,
            $this->landlord,
            $this->tenantUser,
        ] as $user) {

            $response =
                $this
                    ->actingAs(
                        $user,
                        'web'
                    )
                    ->getJson(
                        '/api/me'
                    );

            $response
                ->assertOk();
        }
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        auth()
            ->guard(
                'web'
            )
            ->logout();

        $response =
            $this->getJson(
                '/api/me'
            );

        $response
            ->assertUnauthorized();
    }
}
