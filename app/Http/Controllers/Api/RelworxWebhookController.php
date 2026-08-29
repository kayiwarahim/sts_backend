<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSuccessfulPayment;
use App\Models\Payment;
use App\Services\MobileMoneyPaymentService;
use App\Services\RelworxWebhookSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RelworxWebhookController extends Controller
{
    public function __construct(
        protected RelworxWebhookSignatureService $signatureService,
        protected MobileMoneyPaymentService $paymentService
    ) {}

    public function handle(
        Request $request
    ): JsonResponse {

        /*
        |--------------------------------------------------------------------------
        | Relworx-Signature
        |--------------------------------------------------------------------------
        */

        $signature = $request->header('Relworx-Signature');

        /*
        |--------------------------------------------------------------------------
        | Verify signed request BEFORE processing anything
        |--------------------------------------------------------------------------
        */

        if (
            ! $this
                ->signatureService
                ->verify(
                    $signature,
                    $request->all()
                )
        ) {

            Log::warning(
                'Rejected Relworx webhook: invalid signature.',
                [
                    'ip' => $request->ip(),
                    'customer_reference' => $request->input('customer_reference'),
                    'internal_reference' => $request->input('internal_reference'),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Required signed identifiers
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([
                'status' => [
                    'required',
                    'string',
                ],

                'customer_reference' => [
                    'required',
                    'string',
                ],

                'internal_reference' => [
                    'required',
                    'string',
                ],

                'message' => [
                    'nullable',
                    'string',
                ],

                'msisdn' => [
                    'nullable',
                    'string',
                ],

                'amount' => [
                    'nullable',
                    'numeric',
                ],

                'currency' => [
                    'nullable',
                    'string',
                ],

                'provider' => [
                    'nullable',
                    'string',
                ],

                'charge' => [
                    'nullable',
                    'numeric',
                ],

                'provider_transaction_id' => [
                    'nullable',
                    'string',
                ],

                'completed_at' => [
                    'nullable',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | Find our payment
        |--------------------------------------------------------------------------
        */

        $payment =
            Payment::query()
                ->where(
                    'reference',
                    $validated[
                        'customer_reference'
                    ]
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | Fallback lookup using Relworx internal reference
        |--------------------------------------------------------------------------
        */

        if (! $payment) {

            $payment =
                Payment::query()
                    ->where(
                        'provider_reference',
                        $validated[
                            'internal_reference'
                        ]
                    )
                    ->first();
        }

        /*
        |--------------------------------------------------------------------------
        | Unknown reference
        |--------------------------------------------------------------------------
        |
        | Retry will not fix an unknown payment, so acknowledge the webhook
        | while logging it for investigation.
        |--------------------------------------------------------------------------
        */

        if (! $payment) {

            Log::warning(
                'Relworx webhook received for unknown payment.',
                [
                    'customer_reference' => $validated['customer_reference'],
                    'internal_reference' => $validated['internal_reference'],
                ]
            );

            return response()->json([
                'success' => true,
                'received' => true,
                'processed' => false,
            ], 200);
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Update payment only.
            |
            | STS/ledger processing happens in queued job.
            |--------------------------------------------------------------------------
            */

            $payment =
                $this
                    ->paymentService
                    ->applyProviderResult(
                        $payment,
                        $validated,
                        false
                    );

            /* Successful payment → queue full financial/ST​S pipeline */

            if (
                $payment->status === 'successful'
            ) {

                ProcessSuccessfulPayment::dispatch($payment->id);
            }

            /* Relworx explicitly requires HTTP 200 acknowledgment. */
            return response()->json([
                'success' => true,
                'received' => true,
                'processed' => true,
            ], 200);

        } catch (RuntimeException $e) {

            /*
            |--------------------------------------------------------------------------
            | Valid Relworx webhook but business-data mismatch.
            |
            | Repeating the same webhook won't repair amount/reference mismatch.
            |--------------------------------------------------------------------------
            */

            Log::error(
                'Relworx webhook validation failure.',
                [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                    'payload' => $validated,
                ]
            );

            /*
            | Acknowledge receipt so Relworx does not retry an unsafe payload.
            */

            return response()->json([
                'success' => true,
                'received' => true,
                'processed' => false,
            ], 200);

        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Temporary/system failure.
            |
            | Return non-200 so Relworx retries according to its documented
            | retry strategy.
            |--------------------------------------------------------------------------
            */

            Log::error(
                'Relworx webhook processing failed.',
                [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing temporarily failed.',
            ], 500);
        }
    }
}
