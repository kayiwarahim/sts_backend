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
        $this->baseUrl = rtrim(
            config('services.relworx.base_url'),
            '/'
        );

        $this->accountNo =
            config('services.relworx.account_no');

        $this->bearerToken =
            config('services.relworx.bearer_token');

        $this->timeout =
            (int) config(
                'services.relworx.timeout',
                30
            );

        if (
            !$this->accountNo ||
            !$this->bearerToken
        ) {
            throw new RuntimeException(
                'Relworx credentials are not configured.'
            );
        }
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

        $response = Http::timeout(
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
                        $currency,

                    'amount' =>
                        round($amount, 2),

                    'description' =>
                        $description,
                ]
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                'Relworx HTTP error: ' .
                $response->status() .
                ' - ' .
                $response->body()
            );
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new RuntimeException(
                'Relworx returned an invalid response.'
            );
        }

        if (
            ($data['success'] ?? false)
            !== true
        ) {
            throw new RuntimeException(
                $data['message']
                    ?? 'Relworx payment request failed.'
            );
        }

        if (
            empty(
                $data['internal_reference']
            )
        ) {
            throw new RuntimeException(
                'Relworx did not return an internal reference.'
            );
        }

        return $data;
    }

    /**
     * Check a payment request.
     */
    public function checkRequestStatus(
        string $reference
    ): array {

        $response = Http::timeout(
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

        if (!$response->successful()) {
            throw new RuntimeException(
                'Relworx HTTP error: ' .
                $response->status() .
                ' - ' .
                $response->body()
            );
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new RuntimeException(
                'Relworx returned an invalid response.'
            );
        }

        return $data;
    }
}