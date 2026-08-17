<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class RelworxService
{
    protected string $baseUrl;

    protected string $accountNo;

    protected string $bearerToken;

    protected int $timeout;

    public function __construct()
    {
        /*
        |--------------------------------------------------------------------------
        | Load configuration safely
        |--------------------------------------------------------------------------
        |
        | Do not assign nullable config values directly to typed string
        | properties. Validate them first.
        |
        */

        $baseUrl =
            config(
                'services.relworx.base_url'
            );

        $accountNo =
            config(
                'services.relworx.account_no'
            );

        $bearerToken =
            config(
                'services.relworx.bearer_token'
            );

        $timeout =
            config(
                'services.relworx.timeout',
                30
            );

        /*
        |--------------------------------------------------------------------------
        | Validate required Relworx configuration
        |--------------------------------------------------------------------------
        */

        if (
            !is_string($accountNo)
            ||
            trim($accountNo) === ''
            ||
            !is_string($bearerToken)
            ||
            trim($bearerToken) === ''
        ) {
            throw new RuntimeException(
                'Relworx credentials are not configured.'
            );
        }

        if (
            !is_string($baseUrl)
            ||
            trim($baseUrl) === ''
        ) {
            throw new RuntimeException(
                'Relworx base URL is not configured.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Assign validated configuration
        |--------------------------------------------------------------------------
        */

        $this->baseUrl =
            rtrim(
                $baseUrl,
                '/'
            );

        $this->accountNo =
            trim(
                $accountNo
            );

        $this->bearerToken =
            trim(
                $bearerToken
            );

        $this->timeout =
            max(
                1,
                (int) $timeout
            );
    }

    /**
     * Request money from an MTN/Airtel subscriber.
     */
    public function requestPayment(
        string $reference,
        string $msisdn,
        float $amount,
        string $currency = 'UGX',
        string $description = 'Water payment'
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Validate input
        |--------------------------------------------------------------------------
        */

        if ($amount <= 0) {
            throw new RuntimeException(
                'Payment amount must be greater than zero.'
            );
        }

        if (trim($reference) === '') {
            throw new RuntimeException(
                'Payment reference is required.'
            );
        }

        if (trim($msisdn) === '') {
            throw new RuntimeException(
                'Mobile money number is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Send request to Relworx
        |--------------------------------------------------------------------------
        */

        $response =
            Http::timeout(
                $this->timeout
            )
                ->withToken(
                    $this->bearerToken
                )
                ->acceptJson()
                ->asJson()
                ->post(
                    $this->baseUrl .
                    '/mobile-money/request-payment',
                    [
                        'account_no' =>
                            $this->accountNo,

                        'reference' =>
                            $reference,

                        'msisdn' =>
                            $msisdn,

                        'currency' =>
                            strtoupper(
                                $currency
                            ),

                        'amount' =>
                            round(
                                $amount,
                                2
                            ),

                        'description' =>
                            $description,
                    ]
                );

        /*
        |--------------------------------------------------------------------------
        | Validate HTTP response
        |--------------------------------------------------------------------------
        */

        if (
            !$response->successful()
        ) {
            throw new RuntimeException(
                'Relworx HTTP error: ' .
                $response->status() .
                ' - ' .
                $response->body()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Decode response
        |--------------------------------------------------------------------------
        */

        $data =
            $response->json();

        if (
            !is_array($data)
        ) {
            throw new RuntimeException(
                'Relworx returned an invalid response.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Relworx application response
        |--------------------------------------------------------------------------
        */

        if (
            (
                $data[
                    'success'
                ] ?? false
            )
            !== true
        ) {
            throw new RuntimeException(
                $data[
                    'message'
                ]
                ??
                'Relworx payment request failed.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Relworx must return internal reference
        |--------------------------------------------------------------------------
        */

        if (
            empty(
                $data[
                    'internal_reference'
                ]
            )
        ) {
            throw new RuntimeException(
                'Relworx did not return an internal reference.'
            );
        }

        return $data;
    }

    /**
     * Check payment request status.
     */
    public function checkRequestStatus(
        string $reference
    ): array {
        if (
            trim($reference) === ''
        ) {
            throw new RuntimeException(
                'Relworx internal reference is required.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Query Relworx
        |--------------------------------------------------------------------------
        */

        $response =
            Http::timeout(
                $this->timeout
            )
                ->withToken(
                    $this->bearerToken
                )
                ->acceptJson()
                ->get(
                    $this->baseUrl .
                    '/mobile-money/check-request-status',
                    [
                        'internal_reference' =>
                            $reference,

                        'account_no' =>
                            $this->accountNo,
                    ]
                );

        /*
        |--------------------------------------------------------------------------
        | Validate HTTP response
        |--------------------------------------------------------------------------
        */

        if (
            !$response->successful()
        ) {
            throw new RuntimeException(
                'Relworx HTTP error: ' .
                $response->status() .
                ' - ' .
                $response->body()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Decode response
        |--------------------------------------------------------------------------
        */

        $data =
            $response->json();

        if (
            !is_array($data)
        ) {
            throw new RuntimeException(
                'Relworx returned an invalid response.'
            );
        }

        return $data;
    }
}