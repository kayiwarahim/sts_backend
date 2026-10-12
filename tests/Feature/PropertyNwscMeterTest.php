<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PropertyNwscMeterTest extends TestCase
{
    use RefreshDatabase;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::findOrCreate('Super Admin', 'web');
        foreach (['properties.create', 'properties.update', 'properties.view'] as $name) {
            $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        $organization = Organization::create(['name' => 'NWSC Test', 'status' => 'active']);
        $this->payload = ['name' => 'Test Property', 'property_code' => 'NWSC-TEST', 'organization_id' => $organization->id];
    }

    public function test_account_is_saved_read_updated_and_retained_using_existing_table(): void
    {
        $response = $this->postJson('/api/properties', $this->payload + [
            'nwsc_account' => ['account_number' => '000123', 'account_name' => 'Main Supply', 'meter_number' => '00123456', 'phone' => '0700000000', 'status' => 'active'],
        ])->assertCreated()->assertJsonPath('data.nwsc_accounts.0.meter_number', '00123456')
            ->assertJsonPath('data.organization.id', $this->payload['organization_id'])
            ->assertJsonPath('data.units', [])
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.nwsc_accounts.0.phone', '0700000000')
            ->assertJsonPath('data.nwsc_accounts.0.status', 'active');
        $id = $response->json('data.id');
        $accountId = $response->json('data.nwsc_accounts.0.id');
        $response->assertJsonPath('data.nwsc_accounts.0.property_id', $id);
        $this->assertFalse(Schema::hasColumn('properties', 'nwsc_main_meter_number'));
        $this->getJson('/api/properties/'.$id)->assertOk()->assertJsonPath('data.nwsc_accounts.0.account_number', '000123');
        $this->getJson('/api/properties')->assertOk()->assertJsonPath('data.0.nwsc_accounts.0.account_name', 'Main Supply');
        $this->patchJson('/api/properties/'.$id, ['name' => 'Renamed'])->assertOk()->assertJsonPath('data.nwsc_accounts.0.meter_number', '00123456');
        $this->patchJson('/api/properties/'.$id, ['nwsc_account' => ['account_number' => '000123', 'meter_number' => '009876']])
            ->assertOk()->assertJsonPath('data.nwsc_accounts.0.id', $accountId)->assertJsonPath('data.nwsc_accounts.0.account_name', 'Main Supply')
            ->assertJsonPath('data.organization.id', $this->payload['organization_id'])->assertJsonPath('data.units', []);
        $this->patchJson('/api/properties/'.$id, ['nwsc_account' => ['account_number' => '000123', 'meter_number' => null]])->assertOk();
        $this->assertDatabaseHas('nwsc_accounts', ['id' => $accountId, 'property_id' => $id, 'meter_number' => null]);
        $this->assertDatabaseCount('nwsc_accounts', 1);
    }

    public function test_account_is_optional_and_requires_account_number(): void
    {
        $id = $this->postJson('/api/properties', $this->payload)->assertCreated()->assertJsonPath('data.nwsc_accounts', [])->json('data.id');
        $this->patchJson('/api/properties/'.$id, ['nwsc_account' => ['meter_number' => '123']])
            ->assertUnprocessable()->assertJsonValidationErrors('nwsc_account.account_number');
        $this->patchJson('/api/properties/'.$id, ['nwsc_account' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('nwsc_account');
        $this->postJson('/api/properties', array_merge($this->payload, ['property_code' => 'OTHER', 'nwsc_account' => ['account_number' => '001', 'meter_number' => 123]]))
            ->assertUnprocessable()->assertJsonValidationErrors('nwsc_account.meter_number');
        $this->assertDatabaseCount('nwsc_accounts', 0);
    }
}
