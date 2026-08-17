<?php

namespace Tests\Feature;

use App\Services\RelworxService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class RelworxServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.relworx.base_url' =>
                'https://relworx-test.local/api',

            'services.relworx.account_no' =>
                'REL_TEST_ACCOUNT',

            'services.relworx.bearer_token' =>
                'TEST_BEARER_TOKEN',

            'services.relworx.timeout' =>
                10,
        ]);

        Http::preventStrayRequests();
    }

    public function test_request_payment_sends_correct_relworx_payload(): void
    {
        Http::fake([
            'https://relworx-test.local/api/mobile-money/request-payment' =>
                Http::response([
                    'success' => true,
                    'message' => 'Request payment in progress.',
                    'internal_reference' =>
                        'internal-test-123',
                ], 200),
        ]);

        $result =
            app(RelworxService::class)
                ->requestPayment(
                    'WTR-TEST-001',
                    '+256752225375',
                    1000,
                    'UGX',
                    'Water purchase'
                );

        $this->assertTrue(
            $result['success']
        );

        $this->assertEquals(
            'internal-test-123',
            $result['internal_reference']
        );

        Http::assertSent(
            function (Request $request) {
                return
                    $request->url() ===
                        'https://relworx-test.local/api/mobile-money/request-payment'

                    &&
                    $request->method() ===
                        'POST'

                    &&
                    $request[
                        'account_no'
                    ] ===
                        'REL_TEST_ACCOUNT'

                    &&
                    $request[
                        'reference'
                    ] ===
                        'WTR-TEST-001'

                    &&
                    $request[
                        'msisdn'
                    ] ===
                        '+256752225375'

                    &&
                    $request[
                        'currency'
                    ] ===
                        'UGX'

                    &&
                    (float)
                    $request[
                        'amount'
                    ] ===
                        1000.00

                    &&
                    $request[
                        'description'
                    ] ===
                        'Water purchase'

                    &&
                    $request->hasHeader(
                        'Authorization',
                        'Bearer TEST_BEARER_TOKEN'
                    );
            }
        );
    }

    public function test_request_payment_returns_internal_reference(): void
    {
        Http::fake([
            '*' =>
                Http::response([
                    'success' => true,
                    'message' =>
                        'Request payment in progress.',

                    'internal_reference' =>
                        'relworx-internal-456',
                ], 200),
        ]);

        $result =
            app(RelworxService::class)
                ->requestPayment(
                    'WTR-TEST-002',
                    '+256772000001',
                    500
                );

        $this->assertEquals(
            'relworx-internal-456',
            $result[
                'internal_reference'
            ]
        );
    }

    public function test_request_payment_rejects_relworx_application_failure(): void
    {
        Http::fake([
            '*' =>
                Http::response([
                    'success' => false,
                    'message' =>
                        'Invalid mobile money number.',
                ], 200),
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Invalid mobile money number.'
        );

        app(RelworxService::class)
            ->requestPayment(
                'WTR-TEST-003',
                '+256700000000',
                1000
            );
    }

    public function test_request_payment_rejects_missing_internal_reference(): void
    {
        Http::fake([
            '*' =>
                Http::response([
                    'success' => true,
                    'message' =>
                        'Request accepted.',
                ], 200),
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Relworx did not return an internal reference.'
        );

        app(RelworxService::class)
            ->requestPayment(
                'WTR-TEST-004',
                '+256700000001',
                1000
            );
    }

    public function test_request_payment_rejects_http_error(): void
    {
        Http::fake([
            '*' =>
                Http::response(
                    'Internal provider error',
                    500
                ),
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Relworx HTTP error: 500'
        );

        app(RelworxService::class)
            ->requestPayment(
                'WTR-TEST-005',
                '+256700000001',
                1000
            );
    }

    public function test_check_request_status_sends_correct_query_parameters(): void
    {
        Http::fake([
            'https://relworx-test.local/api/mobile-money/check-request-status*' =>
                Http::response([
                    'success' => true,
                    'status' => 'pending',
                    'request_status' => 'pending',
                    'customer_reference' =>
                        'WTR-TEST-006',
                    'internal_reference' =>
                        'internal-006',
                    'amount' => 1000,
                    'currency' => 'UGX',
                ], 200),
        ]);

        $result =
            app(RelworxService::class)
                ->checkRequestStatus(
                    'internal-006'
                );

        $this->assertEquals(
            'pending',
            $result['status']
        );

        Http::assertSent(
            function (Request $request) {
                return
                    str_contains(
                        $request->url(),
                        '/mobile-money/check-request-status'
                    )

                    &&
                    $request->method() ===
                        'GET'

                    &&
                    $request[
                        'internal_reference'
                    ] ===
                        'internal-006'

                    &&
                    $request[
                        'account_no'
                    ] ===
                        'REL_TEST_ACCOUNT'

                    &&
                    $request->hasHeader(
                        'Authorization',
                        'Bearer TEST_BEARER_TOKEN'
                    );
            }
        );
    }

    public function test_check_request_status_handles_success_response(): void
    {
        Http::fake([
            '*' =>
                Http::response([
                    'success' => true,
                    'status' => 'success',
                    'message' =>
                        'Request payment completed successfully.',

                    'customer_reference' =>
                        'WTR-TEST-007',

                    'internal_reference' =>
                        'internal-007',

                    'msisdn' =>
                        '+256752225375',

                    'amount' =>
                        1000,

                    'currency' =>
                        'UGX',

                    'provider' =>
                        'AIRTEL_UGANDA',

                    'charge' =>
                        30,

                    'request_status' =>
                        'success',

                    'provider_transaction_id' =>
                        'TX-007',

                    'completed_at' =>
                        '2026-08-17T08:00:00+03:00',
                ], 200),
        ]);

        $result =
            app(RelworxService::class)
                ->checkRequestStatus(
                    'internal-007'
                );

        $this->assertEquals(
            'success',
            $result['status']
        );

        $this->assertEquals(
            'AIRTEL_UGANDA',
            $result['provider']
        );

        $this->assertEquals(
            'TX-007',
            $result[
                'provider_transaction_id'
            ]
        );
    }

    public function test_relworx_service_requires_credentials(): void
    {
        config([
            'services.relworx.account_no' =>
                null,

            'services.relworx.bearer_token' =>
                null,
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Relworx credentials are not configured.'
        );

        new RelworxService();
    }
}