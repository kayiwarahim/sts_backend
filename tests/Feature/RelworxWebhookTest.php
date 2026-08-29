<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelworxWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected Property $property;

    protected Tenant $tenant;

    protected PaymentProvider $provider;

    protected string $webhookKey =
        'TEST_WEBHOOK_SIGNING_KEY';

    protected string $webhookUrl =
        'http://localhost/api/webhooks/relworx';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.relworx.webhook_key' => $this->webhookKey,

            'services.relworx.webhook_url' => $this->webhookUrl,

            'services.relworx.webhook_tolerance' => 300,

            /*
            |--------------------------------------------------------------------------
            | Needed because MobileMoneyPaymentService resolves RelworxService.
            |--------------------------------------------------------------------------
            */

            'services.relworx.base_url' => 'https://relworx-test.local/api',

            'services.relworx.account_no' => 'REL_TEST_ACCOUNT',

            'services.relworx.bearer_token' => 'TEST_TOKEN',
        ]);

        $this->organization =
            Organization::create([
                'name' => 'Webhook Organization',

                'registration_number' => 'WEBHOOK-ORG-001',

                'phone' => '+256700000001',

                'email' => 'webhook@example.com',

                'address' => 'Kampala',

                'status' => 'active',
            ]);

        $this->property =
            Property::create([
                'organization_id' => $this->organization->id,

                'name' => 'Webhook Property',

                'property_code' => 'WEBHOOK-PROP-001',

                'address' => 'Kampala',

                'city' => 'Kampala',

                'district' => 'Central',

                'latitude' => 0,

                'longitude' => 0,

                'status' => 'active',
            ]);

        $this->tenant =
            Tenant::create([
                'organization_id' => $this->organization->id,

                'first_name' => 'Webhook',

                'last_name' => 'Tenant',

                'phone' => '256752225375',

                'email' => 'webhook-tenant@example.com',

                'status' => 'active',
            ]);

        $this->provider =
            PaymentProvider::create([
                'name' => 'Relworx',

                'code' => 'RELWORX',

                'type' => 'aggregator',

                'base_url' => 'https://relworx-test.local/api',

                'is_active' => true,

                'configuration' => null,
            ]);
    }

    protected function createPayment(): Payment
    {
        return Payment::create([
            'organization_id' => $this->organization->id,

            'property_id' => $this->property->id,

            'tenant_id' => $this->tenant->id,

            'payment_provider_id' => $this->provider->id,

            'payment_provider_account_id' => null,

            'reference' => 'WTR-WEBHOOK-'.
                strtoupper(
                    uniqid()
                ),

            'provider_reference' => 'REL-INTERNAL-'.
                strtoupper(
                    uniqid()
                ),

            'amount' => 1000,

            'currency' => 'UGX',

            'payer_phone' => '+256752225375',

            'status' => 'processing',

            'initiated_at' => now(),
        ]);
    }

    protected function signature(
        int $timestamp,
        array $payload
    ): string {
        /*
        |--------------------------------------------------------------------------
        | Relworx signs ONLY:
        |
        | status
        | customer_reference
        | internal_reference
        |--------------------------------------------------------------------------
        */

        $params = [
            'status' => $payload['status'] ?? '',

            'customer_reference' => $payload[
                    'customer_reference'
                ] ?? '',

            'internal_reference' => $payload[
                    'internal_reference'
                ] ?? '',
        ];

        ksort(
            $params
        );

        $signedData =
            $this->webhookUrl.
            $timestamp;

        foreach (
            $params as $key => $value
        ) {
            $signedData .=
                (string) $key.
                (string) $value;
        }

        return hash_hmac(
            'sha256',
            $signedData,
            $this->webhookKey,
            false
        );
    }

    protected function header(
        int $timestamp,
        array $payload
    ): string {
        return
            't='.
            $timestamp.
            ',v='.
            $this->signature(
                $timestamp,
                $payload
            );
    }

    public function test_valid_failed_webhook_is_accepted_and_updates_payment(): void
    {
        $payment =
            $this->createPayment();

        $payload = [
            'status' => 'failed',

            'message' => 'Customer declined payment.',

            'customer_reference' => $payment->reference,

            'internal_reference' => $payment->provider_reference,

            'msisdn' => '+256752225375',

            'amount' => 1000,

            'currency' => 'UGX',

            'provider' => 'AIRTEL_UGANDA',

            'charge' => 0,

            'completed_at' => now()->toIso8601String(),
        ];

        $timestamp =
            now()->timestamp;

        $response =
            $this->withHeader(
                'Relworx-Signature',
                $this->header(
                    $timestamp,
                    $payload
                )
            )
                ->postJson(
                    '/api/webhooks/relworx',
                    $payload
                );

        $response->assertOk();

        $payment->refresh();

        $this->assertEquals(
            'failed',
            $payment->status
        );

        $this->assertEquals(
            'Customer declined payment.',
            $payment->failure_reason
        );
    }

    public function test_webhook_without_signature_is_rejected(): void
    {
        $payment =
            $this->createPayment();

        $response =
            $this->postJson(
                '/api/webhooks/relworx',
                [
                    'status' => 'failed',

                    'customer_reference' => $payment->reference,

                    'internal_reference' => $payment->provider_reference,
                ]
            );

        $this->assertTrue(
            in_array(
                $response->status(),
                [
                    400,
                    401,
                    403,
                    422,
                ],
                true
            )
        );

        $this->assertEquals(
            'processing',
            $payment
                ->fresh()
                ->status
        );
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $payment =
            $this->createPayment();

        $response =
            $this->withHeader(
                'Relworx-Signature',
                't='.
                now()->timestamp.
                ',v=INVALID_SIGNATURE'
            )
                ->postJson(
                    '/api/webhooks/relworx',
                    [
                        'status' => 'failed',

                        'customer_reference' => $payment->reference,

                        'internal_reference' => $payment->provider_reference,
                    ]
                );

        $this->assertTrue(
            in_array(
                $response->status(),
                [
                    400,
                    401,
                    403,
                    422,
                ],
                true
            )
        );

        $this->assertEquals(
            'processing',
            $payment
                ->fresh()
                ->status
        );
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $payment =
            $this->createPayment();

        $originalPayload = [
            'status' => 'failed',

            'customer_reference' => $payment->reference,

            'internal_reference' => $payment->provider_reference,
        ];

        $timestamp =
            now()->timestamp;

        /*
        |--------------------------------------------------------------------------
        | Sign failed...
        |--------------------------------------------------------------------------
        */

        $header =
            $this->header(
                $timestamp,
                $originalPayload
            );

        /*
        |--------------------------------------------------------------------------
        | ...but send success.
        |--------------------------------------------------------------------------
        */

        $tamperedPayload =
            $originalPayload;

        $tamperedPayload[
            'status'
        ] = 'success';

        $response =
            $this->withHeader(
                'Relworx-Signature',
                $header
            )
                ->postJson(
                    '/api/webhooks/relworx',
                    $tamperedPayload
                );

        $this->assertTrue(
            in_array(
                $response->status(),
                [
                    400,
                    401,
                    403,
                    422,
                ],
                true
            )
        );

        $this->assertEquals(
            'processing',
            $payment
                ->fresh()
                ->status
        );
    }

    public function test_old_webhook_timestamp_is_rejected(): void
    {
        $payment =
            $this->createPayment();

        $payload = [
            'status' => 'failed',

            'customer_reference' => $payment->reference,

            'internal_reference' => $payment->provider_reference,
        ];

        /*
        |--------------------------------------------------------------------------
        | Older than configured 300 second tolerance.
        |--------------------------------------------------------------------------
        */

        $timestamp =
            now()
                ->subMinutes(10)
                ->timestamp;

        $response =
            $this->withHeader(
                'Relworx-Signature',
                $this->header(
                    $timestamp,
                    $payload
                )
            )
                ->postJson(
                    '/api/webhooks/relworx',
                    $payload
                );

        $this->assertTrue(
            in_array(
                $response->status(),
                [
                    400,
                    401,
                    403,
                    422,
                ],
                true
            )
        );

        $this->assertEquals(
            'processing',
            $payment
                ->fresh()
                ->status
        );
    }

    public function test_same_failed_webhook_can_be_received_twice_safely(): void
    {
        $payment =
            $this->createPayment();

        $payload = [
            'status' => 'failed',

            'message' => 'Payment failed.',

            'customer_reference' => $payment->reference,

            'internal_reference' => $payment->provider_reference,

            'provider' => 'AIRTEL_UGANDA',
        ];

        $timestampOne =
            now()->timestamp;

        $first =
            $this->withHeader(
                'Relworx-Signature',
                $this->header(
                    $timestampOne,
                    $payload
                )
            )
                ->postJson(
                    '/api/webhooks/relworx',
                    $payload
                );

        $first->assertOk();

        /*
        |--------------------------------------------------------------------------
        | Relworx may retry if acknowledgment is lost.
        |--------------------------------------------------------------------------
        */

        $timestampTwo =
            now()->timestamp;

        $second =
            $this->withHeader(
                'Relworx-Signature',
                $this->header(
                    $timestampTwo,
                    $payload
                )
            )
                ->postJson(
                    '/api/webhooks/relworx',
                    $payload
                );

        $second->assertOk();

        $this->assertEquals(
            1,
            Payment::where(
                'reference',
                $payment->reference
            )->count()
        );

        $this->assertEquals(
            'failed',
            $payment
                ->fresh()
                ->status
        );
    }
}
