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

    public function __construct(bool $validate = true)
    {
        $this->baseUrl = rtrim((string) config('services.relworx.base_url', ''), '/');
        $this->accountNo = trim((string) config('services.relworx.account_no', ''));
        $this->bearerToken = trim((string) config('services.relworx.bearer_token', ''));
        $this->timeout = max(1, (int) config('services.relworx.timeout', 30));

        if ($validate) {
            $this->ensureConfigured();
        }
    }

    /**
     * Ensure credentials are configured before making API calls.
     */
    protected function ensureConfigured(): void
    {
        if ($this->baseUrl === '' || $this->accountNo === '' || $this->bearerToken === '') {
            throw new RuntimeException('Relworx credentials are not configured.');
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
        $this->ensureConfigured();

        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }
        if (trim($reference) === '') {
            throw new RuntimeException('Payment reference is required.');
        }
        if (trim($msisdn) === '') {
            throw new RuntimeException('Mobile money number is required.');
        }

        $response = Http::timeout($this->timeout)
            ->withToken($this->bearerToken)
            ->acceptJson()->asJson()
            ->post($this->baseUrl.'/mobile-money/request-payment', [
                'account_no' => $this->accountNo,
                'reference' => $reference,
                'msisdn' => $msisdn,
                'currency' => strtoupper($currency),
                'amount' => round($amount, 2),
                'description' => $description,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Relworx HTTP error: '.$response->status().' - '.$response->body()
            );
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Relworx returned an invalid response.');
        }

        if (($data['success'] ?? false) !== true) {
            throw new RuntimeException($data['message'] ?? 'Relworx payment request failed.');
        }

        if (empty($data['internal_reference'])) {
            throw new RuntimeException('Relworx did not return an internal reference.');
        }

        return $data;
    }

    /**
     * Check payment request status.
     */
    public function checkRequestStatus(string $reference): array
    {
        $this->ensureConfigured();

        if (trim($reference) === '') {
            throw new RuntimeException('Relworx internal reference is required.');
        }

        $response = Http::timeout($this->timeout)
            ->withToken($this->bearerToken)
            ->acceptJson()
            ->get($this->baseUrl.'/mobile-money/check-request-status', [
                'internal_reference' => $reference,
                'account_no' => $this->accountNo,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Relworx HTTP error: '.$response->status().' - '.$response->body()
            );
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Relworx returned an invalid response.');
        }

        return $data;
    }
}
