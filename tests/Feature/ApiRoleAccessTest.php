<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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
        | Roles
        |--------------------------------------------------------------------------
        */

        foreach ([
            'Super Admin',
            'Landlord',
            'Tenant',
        ] as $role) {
            Role::findOrCreate(
                $role,
                'web'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Permissions used by protected management routes
        |--------------------------------------------------------------------------
        */

        $permissions = [
            'properties.view',
            'properties.create',
            'properties.update',
            'properties.delete',

            'units.view',
            'units.create',
            'units.update',
            'units.delete',

            'tenants.view',
            'tenants.create',
            'tenants.update',
            'tenants.delete',

            'tenancies.view',
            'tenancies.create',
            'tenancies.update',
            'tenancies.delete',

            'meters.view',
            'meters.create',
            'meters.update',
            'meters.delete',

            'meter_assignments.view',
            'meter_assignments.create',
            'meter_assignments.update',
            'meter_assignments.delete',

            'water_tariffs.view',
            'water_tariffs.create',
            'water_tariffs.update',
            'water_tariffs.delete',

            'billing_configurations.view',
            'billing_configurations.create',
            'billing_configurations.update',
            'billing_configurations.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Landlord management permissions
        |--------------------------------------------------------------------------
        */

        Role::findByName(
            'Landlord',
            'web'
        )->givePermissionTo(
            $permissions
        );

        /*
        |--------------------------------------------------------------------------
        | Super Admin receives all permissions
        |--------------------------------------------------------------------------
        */

        Role::findByName(
            'Super Admin',
            'web'
        )->givePermissionTo(
            $permissions
        );

        /*
        |--------------------------------------------------------------------------
        | Organization
        |--------------------------------------------------------------------------
        */

        $this->organization =
            Organization::create([
                'name' => 'Authorization Test Organization',

                'registration_number' => 'AUTH-ORG-001',

                'phone' => '+256700000001',

                'email' => 'auth-org@example.com',

                'address' => 'Kampala',

                'status' => 'active',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        $this->superAdmin =
            User::create([
                'organization_id' => null,

                'name' => 'Super Admin',

                'email' => 'superadmin-auth@example.com',

                'password' => Hash::make(
                    'password'
                ),

                'email_verified_at' => now(),
            ]);

        $this->superAdmin
            ->assignRole(
                'Super Admin'
            );

        /*
        |--------------------------------------------------------------------------
        | Landlord
        |--------------------------------------------------------------------------
        */

        $this->landlord =
            User::create([
                'organization_id' => $this->organization->id,

                'name' => 'Test Landlord',

                'email' => 'landlord-auth@example.com',

                'password' => Hash::make(
                    'password'
                ),

                'email_verified_at' => now(),
            ]);

        $this->landlord
            ->assignRole(
                'Landlord'
            );

        /*
        |--------------------------------------------------------------------------
        | Tenant user
        |--------------------------------------------------------------------------
        */

        $this->tenantUser =
            User::create([
                'organization_id' => $this->organization->id,

                'name' => 'Test Tenant',

                'email' => 'tenant-auth@example.com',

                'password' => Hash::make(
                    'password'
                ),

                'email_verified_at' => now(),
            ]);

        $this->tenantUser
            ->assignRole(
                'Tenant'
            );

        /*
        |--------------------------------------------------------------------------
        | Tenant profile
        |--------------------------------------------------------------------------
        */

        $this->tenant =
            Tenant::create([
                'organization_id' => $this->organization->id,

                'user_id' => $this->tenantUser->id,

                'first_name' => 'Test',

                'last_name' => 'Tenant',

                'phone' => '256700000003',

                'email' => $this->tenantUser->email,

                'status' => 'active',
            ]);
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
