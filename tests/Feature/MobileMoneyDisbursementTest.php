<?php

namespace Tests\Feature;

use App\Jobs\SendMobileMoneyDisbursement;
use App\Models\{LedgerAccount, LedgerTransaction, MobileMoneyDisbursement, NwscAccount, Organization, Property, User, WaterWallet};
use App\Services\{DisbursementFeeService, MobileMoneyDisbursementService, RelworxWebhookSignatureService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Queue};
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class MobileMoneyDisbursementTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;
    private WaterWallet $wallet;
    private NwscAccount $account;
    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        config(['services.relworx.base_url' => 'https://provider.test/api', 'services.relworx.account_no' => 'TEST',
            'services.relworx.bearer_token' => 'TEST', 'services.relworx.webhook_key' => 'TEST',
            'services.relworx.webhook_url' => 'https://backend.test/api/webhooks/relworx']);
        $role = Role::findOrCreate('Landlord', 'web');
        foreach (['properties.view', 'nwsc.payments.create', 'nwsc.payments.process', 'nwsc.payments.view'] as $name) {
            $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        $org = Organization::create(['name' => 'Payout Organization']);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole($role); $this->actingAs($user);
        $this->property = Property::create(['organization_id' => $org->id, 'name' => 'Payout Property', 'property_code' => 'PAYOUT']);
        $this->wallet = WaterWallet::create(['property_id' => $this->property->id, 'currency' => 'UGX', 'balance' => 100000, 'status' => 'active']);
        $this->account = NwscAccount::create(['property_id' => $this->property->id, 'account_number' => 'NWSC-1', 'status' => 'active']);
        foreach (['WATER_PAYABLE' => 'liability', 'PAYMENT_CLEARING' => 'asset'] as $code => $type) {
            LedgerAccount::create(['organization_id' => $org->id, 'code' => $code, 'name' => $code, 'type' => $type, 'currency' => 'UGX', 'is_active' => true]);
        }
        $this->payload = ['msisdn' => '+256701345672', 'amount' => 50000, 'nwsc_account_id' => $this->account->id];
    }

    private function url(): string { return '/api/properties/'.$this->property->id.'/disbursements'; }

    private function initiate(): MobileMoneyDisbursement
    {
        $this->postJson($this->url(), array_merge($this->payload, ['reference' => 'CLIENT-SUPPLIED-REFERENCE']))
            ->assertAccepted()
            ->assertJsonPath('data.gateway_fee', '800.00')
            ->assertJsonPath('data.reference', fn ($reference) => str_starts_with($reference, 'DISB-'))
            ->assertJsonPath('data.reference', fn ($reference) => $reference !== 'CLIENT-SUPPLIED-REFERENCE');

        return MobileMoneyDisbursement::sole();
    }

    private function providerResult(MobileMoneyDisbursement $record, string $status = 'success'): array
    {
        return ['customer_reference' => $record->reference, 'internal_reference' => 'PROVIDER-1',
            'msisdn' => $this->payload['msisdn'], 'amount' => 50000, 'currency' => 'UGX',
            'status' => $status, 'charge' => $status === 'success' ? 800 : 0, 'provider_transaction_id' => 'TID-001'];
    }

    private function assertBalanced(): void
    {
        foreach (LedgerTransaction::all() as $journal) {
            $this->assertSame($journal->totalDebits(), $journal->totalCredits());
        }
    }

    public function test_all_fee_band_boundaries(): void
    {
        $fees = app(DisbursementFeeService::class);
        foreach ([500 => 800, 50000 => 800, 50001 => 1600, 200000 => 1600, 200001 => 2100, 400000 => 2100,
            400001 => 3100, 1000000 => 3100, 1000001 => 5200, 1500000 => 5200, 1500001 => 7200, 5000000 => 7200] as $amount => $fee) {
            $this->assertSame($fee, $fees->fee($amount));
            $this->getJson($this->url().'/quote?amount='.$amount)->assertOk()->assertJsonPath('data.total_debit', $amount + $fee);
        }
    }

    public function test_reservation_success_and_duplicate_callbacks_balance_records(): void
    {
        $record = $this->initiate();
        $this->assertSame('49200.00', $this->wallet->fresh()->balance);
        Queue::assertPushed(SendMobileMoneyDisbursement::class);
        $this->assertDatabaseCount('mobile_money_disbursements', 1);
        $this->assertDatabaseCount('water_wallet_transactions', 1);
        Http::fake(['*/send-payment' => Http::response(['success' => true, 'internal_reference' => 'PROVIDER-1'])]);
        $service = app(MobileMoneyDisbursementService::class);
        $service->send($record->id); $service->send($record->id);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['amount'] === 50000 && $request['currency'] === 'UGX' &&
            $request->hasHeader('Accept', 'application/vnd.relworx.v2'));
        $service->applyResult($record, $this->providerResult($record));
        $service->applyResult($record, $this->providerResult($record));
        $this->assertSame('successful', $record->fresh()->status);
        $this->assertSame('TID-001', $record->fresh()->provider_transaction_id);
        $this->assertSame('49200.00', $this->wallet->fresh()->balance);
        $this->assertDatabaseCount('nwsc_payments', 1);
        $this->assertDatabaseHas('nwsc_payments', ['amount' => 50000, 'status' => 'successful']);
        $this->assertDatabaseCount('ledger_transactions', 2);
        $this->assertDatabaseHas('water_wallet_transactions', ['nwsc_payment_id' => $record->fresh()->nwsc_payment_id, 'amount' => 50800]);
        $this->assertBalanced();
    }

    public function test_confirmed_failure_releases_amount_and_fee_once(): void
    {
        $record = $this->initiate();
        $record->update(['status' => 'processing', 'provider_reference' => 'PROVIDER-1']);
        $service = app(MobileMoneyDisbursementService::class);
        $service->applyResult($record, $this->providerResult($record, 'failed'));
        $service->applyResult($record, $this->providerResult($record, 'failed'));
        $this->assertSame('100000.00', $this->wallet->fresh()->balance);
        $this->assertDatabaseCount('water_wallet_transactions', 2);
        $this->assertDatabaseCount('nwsc_payments', 0);
        $this->assertDatabaseCount('ledger_transactions', 2);
        $this->assertBalanced();
    }

    public function test_unknown_submission_is_not_resent_and_can_be_reconciled(): void
    {
        $record = $this->initiate();
        Http::fake(['*/send-payment' => Http::failedConnection(), '*/check-request-status*' => Http::response($this->providerResult($record))]);
        $service = app(MobileMoneyDisbursementService::class);
        $service->send($record->id); $service->send($record->id);
        $this->assertSame('unknown', $record->fresh()->status);
        $this->assertSame('49200.00', $this->wallet->fresh()->balance);
        $this->postJson($this->url().'/'.$record->id.'/refresh')->assertOk()->assertJsonPath('data.status', 'successful');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'check-request-status') && $request['internal_reference'] === $record->reference);
        $this->assertBalanced();
    }

    public function test_fee_discrepancy_retains_reservation_for_review(): void
    {
        $record = $this->initiate();
        $record->update(['status' => 'processing']);
        $result = $this->providerResult($record); $result['charge'] = 900;
        app(MobileMoneyDisbursementService::class)->applyResult($record, $result);
        $this->assertSame('needs_review', $record->fresh()->status);
        $this->assertSame('49200.00', $this->wallet->fresh()->balance);
        $this->assertDatabaseCount('nwsc_payments', 0);
        $this->assertBalanced();
    }

    public function test_validation_access_and_insufficient_funds_are_rejected(): void
    {
        foreach ([499, 5000001, 500.5] as $amount) {
            $this->postJson($this->url(), array_merge($this->payload, ['amount' => $amount]))->assertUnprocessable();
        }
        $this->wallet->update(['balance' => 50000]);
        $this->postJson($this->url(), $this->payload)->assertUnprocessable();
        $this->assertDatabaseCount('mobile_money_disbursements', 0);
        $this->wallet->update(['balance' => 100000]);
        $this->initiate();
        $this->postJson($this->url(), array_merge($this->payload, ['amount' => 50001]))->assertUnprocessable();
        $other = Organization::create(['name' => 'Private']);
        $private = Property::create(['organization_id' => $other->id, 'name' => 'Private', 'property_code' => 'PRIVATE']);
        $this->postJson('/api/properties/'.$private->id.'/disbursements', $this->payload)->assertForbidden();
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.total', 1);
        $this->assertBalanced();
    }

    public function test_provider_mismatches_do_not_post_or_release_funds(): void
    {
        $record = $this->initiate();
        $record->update(['status' => 'processing', 'provider_reference' => 'PROVIDER-1']);
        foreach (['customer_reference' => 'OTHER-REF', 'internal_reference' => 'OTHER', 'msisdn' => '+256701111111', 'currency' => 'USD', 'amount' => 49999] as $field => $value) {
            $result = $this->providerResult($record); $result[$field] = $value;
            try {
                app(MobileMoneyDisbursementService::class)->applyResult($record, $result);
                $this->fail('Provider mismatch should be rejected.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('match', $e->getMessage());
            }
        }
        $this->assertSame('processing', $record->fresh()->status);
        $this->assertSame('49200.00', $this->wallet->fresh()->balance);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('nwsc_payments', 0);
    }

    public function test_foreign_account_and_frozen_wallet_are_rejected(): void
    {
        $other = Property::create(['organization_id' => $this->property->organization_id, 'name' => 'Other', 'property_code' => 'OTHER']);
        $account = NwscAccount::create(['property_id' => $other->id, 'account_number' => 'OTHER']);
        $this->postJson($this->url(), array_merge($this->payload, ['nwsc_account_id' => $account->id]))->assertUnprocessable();
        $this->wallet->update(['status' => 'frozen']);
        $this->postJson($this->url(), $this->payload)->assertUnprocessable();
        $this->assertDatabaseCount('mobile_money_disbursements', 0);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_invalid_signature_cannot_finalize_disbursement(): void
    {
        $record = $this->initiate();
        $this->mock(RelworxWebhookSignatureService::class, fn ($mock) => $mock->shouldReceive('verify')->once()->andReturnFalse());
        $this->postJson('/api/webhooks/relworx', $this->providerResult($record))->assertUnauthorized();
        $this->assertSame('pending', $record->fresh()->status);
        $this->assertDatabaseCount('nwsc_payments', 0);
        Http::assertNothingSent();
    }

    public function test_verified_webhook_routes_to_disbursement_without_collection_processing(): void
    {
        $record = $this->initiate();
        $record->update(['status' => 'processing', 'provider_reference' => 'PROVIDER-1']);
        $this->mock(RelworxWebhookSignatureService::class, fn ($mock) => $mock->shouldReceive('verify')->once()->andReturnTrue());
        Http::fake(['*/check-request-status*' => Http::response($this->providerResult($record))]);
        $this->postJson('/api/webhooks/relworx', $this->providerResult($record))->assertOk()->assertJsonPath('processed', true);
        $this->assertSame('successful', $record->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertBalanced();
    }
}
