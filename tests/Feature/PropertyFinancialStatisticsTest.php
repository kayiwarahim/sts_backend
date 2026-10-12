<?php

namespace Tests\Feature;

use App\Models\{BillingConfiguration, Meter, NwscAccount, NwscPayment, Organization, Payment, PaymentAllocation, PaymentProvider, Property, StsTransaction, Tenant, User, WaterTariff, WaterVending, WaterWallet};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class PropertyFinancialStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;
    private User $user;
    private Tenant $tenant;
    private PaymentProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::findOrCreate('Landlord', 'web');
        foreach (['properties.view', 'reports.view'] as $name) {
            $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        $organization = Organization::create(['name' => 'Financial Test']);
        $this->user = User::factory()->create(['organization_id' => $organization->id]);
        $this->user->assignRole($role);
        $this->actingAs($this->user);
        $this->property = Property::create(['organization_id' => $organization->id, 'name' => 'Test Property', 'property_code' => 'FIN-1']);
        $this->tenant = Tenant::create(['organization_id' => $organization->id, 'first_name' => 'Jane', 'last_name' => 'Tenant', 'phone' => '0700000000']);
        $this->provider = PaymentProvider::create(['name' => 'Test', 'code' => 'TEST', 'type' => 'aggregator', 'is_active' => true]);
    }

    private function payment(string $reference, string $status = 'successful', string $currency = 'UGX'): Payment
    {
        return Payment::create([
            'organization_id' => $this->property->organization_id, 'property_id' => $this->property->id,
            'tenant_id' => $this->tenant->id, 'payment_provider_id' => $this->provider->id,
            'reference' => $reference, 'amount' => '10000.00', 'status' => $status,
            'currency' => $currency, 'initiated_at' => now(),
        ]);
    }

    private function url(): string
    {
        return '/api/properties/'.$this->property->id.'/financial-statistics';
    }

    public function test_totals_use_water_allocations_and_actual_units_without_counting_sts_retries(): void
    {
        $payment = $this->payment('SUCCESS');
        $failed = $this->payment('FAILED', 'failed');
        $this->payment('PENDING', 'pending');
        $this->payment('REFUNDED', 'refunded');
        $configuration = BillingConfiguration::create(['property_id' => $this->property->id, 'name' => 'Split', 'water_percentage' => 75, 'effective_from' => now(), 'status' => 'active']);
        foreach ([[$payment, 'water', 7500], [$payment, 'service_fee', 2500], [$failed, 'water', 7500]] as [$p, $type, $amount]) {
            PaymentAllocation::create(['payment_id' => $p->id, 'billing_configuration_id' => $configuration->id, 'allocation_type' => $type, 'percentage' => 75, 'amount' => $amount]);
        }
        $meter = Meter::create(['organization_id' => $this->property->organization_id, 'meter_number' => '0012345']);
        $tariff = WaterTariff::create(['property_id' => $this->property->id, 'name' => 'Tariff', 'price_per_m3' => 3000, 'effective_from' => now(), 'status' => 'active']);
        WaterVending::create(['payment_id' => $payment->id, 'tenant_id' => $this->tenant->id, 'property_id' => $this->property->id, 'meter_id' => $meter->id, 'water_tariff_id' => $tariff->id, 'amount' => 7500, 'price_per_m3' => 3000, 'volume_m3' => '2.400', 'reference' => 'VEND', 'status' => 'successful']);
        foreach (['failed', 'successful'] as $status) {
            StsTransaction::create(['payment_id' => $payment->id, 'meter_id' => $meter->id, 'reference' => 'STS-'.$status, 'transaction_type' => 'token_generation', 'status' => $status, 'amount' => 7500, 'volume_m3' => '2.500']);
        }
        $wallet = WaterWallet::create(['property_id' => $this->property->id, 'currency' => 'UGX', 'balance' => 5000, 'status' => 'active']);
        $account = NwscAccount::create(['property_id' => $this->property->id, 'account_number' => 'NWSC-1', 'account_name' => 'Main Supply', 'meter_number' => 'MAIN-1', 'status' => 'active']);
        foreach (['successful', 'failed'] as $status) {
            NwscPayment::create(['nwsc_account_id' => $account->id, 'water_wallet_id' => $wallet->id, 'reference' => 'NWSC-'.$status, 'amount' => 2500, 'payment_method' => 'manual', 'status' => $status, 'initiated_by' => $this->user->id]);
        }
        $response = $this->getJson($this->url().'?per_page=1')->assertOk()
            ->assertJsonPath('data.summary_by_currency.0.transaction_count', 4)
            ->assertJsonPath('data.summary_by_currency.0.successful_count', 1)
            ->assertJsonPath('data.summary_by_currency.0.amount_paid', '10000.00')
            ->assertJsonPath('data.summary_by_currency.0.water_allocated', '7500.00')
            ->assertJsonPath('data.summary_by_currency.0.units_delivered_m3', '2.400')
            ->assertJsonPath('data.tenants.0.tenant_name', 'Jane Tenant')
            ->assertJsonPath('data.remittance.amount_available_to_remit', '5000.00')
            ->assertJsonPath('data.nwsc_accounts.0.successful_remitted_amount', '2500.00')
            ->assertJsonPath('data.transactions.total', 4)
            ->assertJsonCount(1, 'data.transactions.data');
        $rows = $this->getJson($this->url())->assertOk()->json('data.transactions.data');
        $row = collect($rows)->firstWhere('id', $payment->id);
        $this->assertNull($row['meter_number']);
        $this->assertSame([], $row['meter_numbers']);
        $this->assertSame('2.400', $row['units_delivered_m3']);
        $this->assertCount(2, $row['sts_transactions']);
        $this->assertSame('0.00', collect($rows)->firstWhere('id', $failed->id)['successful_amount_paid']);
    }

    public function test_empty_property_missing_allocations_currency_groups_and_ambiguous_accounts(): void
    {
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.summary_by_currency', [])
            ->assertJsonPath('data.remittance.amount_available_to_remit', null);
        $this->payment('UGX');
        $this->payment('USD', 'successful', 'USD');
        foreach (['A', 'B'] as $number) {
            NwscAccount::create(['property_id' => $this->property->id, 'account_number' => $number]);
        }
        WaterWallet::create(['property_id' => $this->property->id, 'balance' => 100, 'currency' => 'UGX', 'status' => 'active']);
        $response = $this->getJson($this->url())->assertOk()->assertJsonCount(2, 'data.summary_by_currency')
            ->assertJsonPath('data.remittance.account_selection_required', true)
            ->assertJsonPath('data.remittance.destination_account_id', null)
            ->assertJsonPath('data.remittance.amount_available_to_remit', null);
        foreach ($response->json('data.summary_by_currency') as $summary) {
            $this->assertSame('10000.00', $summary['amount_paid']);
            $this->assertSame(1, $summary['missing_water_allocation_count']);
        }
    }

    public function test_all_properties_report_is_scoped_and_has_independent_transaction_pagination(): void
    {
        $this->payment('ONE');
        $this->payment('TWO', 'failed');
        $second = Property::create(['organization_id' => $this->property->organization_id, 'name' => 'Second', 'property_code' => 'SECOND']);
        $other = Organization::create(['name' => 'Other']);
        Property::create(['organization_id' => $other->id, 'name' => 'Private', 'property_code' => 'PRIVATE']);
        $url = '/api/properties/financial-statistics';
        $this->getJson($url.'?per_page=1&transactions_per_page=1')->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.property.id', $this->property->id)
            ->assertJsonPath('data.data.0.transactions.total', 2)
            ->assertJsonCount(1, 'data.data.0.transactions.data')
            ->assertJsonPath('data.data.0.summary_by_currency.0.transaction_count', 2);
        $this->getJson($url.'?per_page=1&page=2')->assertOk()
            ->assertJsonPath('data.data.0.property.id', $second->id)
            ->assertJsonPath('data.data.0.transactions.current_page', 1);
        $this->getJson($url.'?transactions_page=2&transactions_per_page=1')->assertOk()
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonPath('data.data.0.transactions.current_page', 2);
        foreach (['per_page=101', 'page=0', 'transactions_per_page=0', 'transactions_page=0'] as $query) {
            $this->getJson($url.'?'.$query)->assertUnprocessable();
        }
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 3);
        $this->actingAs(User::factory()->create(['organization_id' => $other->id]))
            ->getJson($url)->assertForbidden();
    }

    public function test_only_current_active_tenant_meters_are_returned_even_for_old_transactions(): void
    {
        $unit = \App\Models\Unit::create(['property_id' => $this->property->id, 'unit_number' => '1']);
        \App\Models\Tenancy::create(['unit_id' => $unit->id, 'tenant_id' => $this->tenant->id, 'start_date' => now()->subMonth(), 'status' => 'active']);
        $old = Meter::create(['organization_id' => $this->property->organization_id, 'meter_number' => 'OLD-001']);
        $current = Meter::create(['organization_id' => $this->property->organization_id, 'meter_number' => 'NEW-001']);
        \App\Models\MeterAssignment::create(['unit_id' => $unit->id, 'meter_id' => $old->id, 'assigned_at' => now()->subMonth(), 'unassigned_at' => now()->subDay(), 'status' => 'ended']);
        \App\Models\MeterAssignment::create(['unit_id' => $unit->id, 'meter_id' => $current->id, 'assigned_at' => now()->subDay(), 'status' => 'active']);
        $historical = $this->payment('OLD-PAY', 'failed');
        $historical->update(['initiated_at' => now()->subDays(2)]);
        $this->payment('CURRENT-PAY', 'pending');
        $stsPayment = $this->payment('STS-PAY');
        StsTransaction::create(['payment_id' => $stsPayment->id, 'meter_id' => $old->id, 'reference' => 'STS-ONLY', 'transaction_type' => 'token_generation', 'status' => 'failed']);
        $response = $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.tenants.0.meter_number', 'NEW-001')
            ->assertJsonPath('data.tenants.0.current_meter_numbers', ['NEW-001']);
        $rows = collect($response->json('data.transactions.data'));
        $this->assertSame('NEW-001', $rows->firstWhere('reference', 'OLD-PAY')['meter_number']);
        $this->assertSame('current_active_assignment', $rows->firstWhere('reference', 'OLD-PAY')['meter_number_source']);
        $this->assertSame('NEW-001', $rows->firstWhere('reference', 'CURRENT-PAY')['meter_number']);
        $this->assertSame('NEW-001', $rows->firstWhere('reference', 'STS-PAY')['meter_number']);
        $this->assertSame('current_active_assignment', $rows->firstWhere('reference', 'STS-PAY')['meter_number_source']);
        $this->getJson('/api/properties/financial-statistics')->assertOk()
            ->assertJsonPath('data.data.0.tenants.0.meter_number', 'NEW-001');
        $this->assertSame('NEW-001', $rows->firstWhere('reference', 'STS-PAY')['sts_transactions'][0]['meter_number']);
        \App\Models\MeterAssignment::where('meter_id', $current->id)->update(['status' => 'ended', 'unassigned_at' => now()]);
        $response = $this->getJson($this->url())->assertOk()
            ->assertJsonPath('data.tenants.0.meter_number', null)
            ->assertJsonPath('data.tenants.0.meter_numbers', []);
        foreach ($response->json('data.transactions.data') as $row) {
            $this->assertNull($row['meter_number']);
            $this->assertSame([], $row['meter_numbers']);
        }
    }

    public function test_statistics_include_only_active_properties(): void
    {
        foreach (['inactive', 'suspended'] as $status) {
            $property = Property::create([
                'organization_id' => $this->property->organization_id,
                'name' => $status, 'property_code' => strtoupper($status), 'status' => $status,
            ]);
            $this->getJson('/api/properties/'.$property->id.'/financial-statistics')->assertNotFound();
        }
        $this->getJson('/api/properties/financial-statistics')->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.property.id', $this->property->id);
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        $this->getJson('/api/properties/financial-statistics')->assertOk()->assertJsonPath('data.total', 1);
        $this->property->update(['status' => 'inactive']);
        $this->getJson('/api/properties/financial-statistics')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson($this->url())->assertNotFound();
    }

    public function test_access_and_pagination_validation(): void
    {
        $other = Organization::create(['name' => 'Other']);
        $property = Property::create(['organization_id' => $other->id, 'name' => 'Other', 'property_code' => 'OTHER']);
        $this->getJson('/api/properties/'.$property->id.'/financial-statistics')->assertForbidden();
        $this->getJson($this->url().'?per_page=101')->assertUnprocessable();
        $this->getJson($this->url().'?page=0')->assertUnprocessable();
        $this->getJson('/api/properties/999999/financial-statistics')->assertNotFound();
        $this->actingAs(User::factory()->create(['organization_id' => $other->id]))->getJson($this->url())->assertForbidden();
    }
}
