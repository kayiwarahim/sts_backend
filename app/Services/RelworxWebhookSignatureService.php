<?php

namespace App\Services;

use RuntimeException;

class RelworxWebhookSignatureService
{
    protected string $webhookKey;

    protected string $webhookUrl;

    protected int $tolerance;

    public function __construct()
    {
        $this->webhookKey =
            (string) config(
                'services.relworx.webhook_key'
            );

        $this->webhookUrl =
            (string) config(
                'services.relworx.webhook_url'
            );

        $this->tolerance =
            (int) config(
                'services.relworx.webhook_tolerance',
                300
            );

        if (! $this->webhookKey) {
            throw new RuntimeException(
                'Relworx webhook key is not configured.'
            );
        }

        if (! $this->webhookUrl) {
            throw new RuntimeException(
                'Relworx webhook URL is not configured.'
            );
        }
    }

    /**
     * Verify the Relworx-Signature header.
     */
    public function verify(
        ?string $signatureHeader,
        array $payload
    ): bool {

        if (! $signatureHeader) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Parse:
        |
        | t=1561370460,v=signature
        |--------------------------------------------------------------------------
        */

        $components =
            $this->parseSignatureHeader(
                $signatureHeader
            );

        $timestamp =
            $components['t']
                ?? null;

        $providedSignature =
            $components['v']
                ?? null;

        if (
            ! $timestamp ||
            ! $providedSignature
        ) {
            return false;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Replay protection
        |--------------------------------------------------------------------------
        */

        $timestampInteger =
            (int) $timestamp;

        if (
            abs(
                time() -
                $timestampInteger
            )
            >
            $this->tolerance
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Relworx specifies ONLY these POST parameters
        |--------------------------------------------------------------------------
        */

        $params = [
            'status' => $payload['status']
                    ?? '',

            'customer_reference' => $payload[
                    'customer_reference'
                ] ?? '',

            'internal_reference' => $payload[
                    'internal_reference'
                ] ?? '',
        ];

        /*
        |--------------------------------------------------------------------------
        | Generate documented HMAC-SHA256 hex signature
        |--------------------------------------------------------------------------
        */

        $expectedHex =
            $this->generateSignature(
                $timestamp,
                $params
            );

        if (
            hash_equals(
                $expectedHex,
                $providedSignature
            )
        ) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Compatibility with sample-style base64 signatures
        |--------------------------------------------------------------------------
        |
        | Relworx's text says hex-encoded HMAC-SHA256, but their example
        | signature visually resembles base64.
        |
        | We primarily verify the documented hex representation and also
        | safely support the equivalent base64 HMAC representation.
        |--------------------------------------------------------------------------
        */

        $signedData =
            $this->buildSignedData(
                $timestamp,
                $params
            );

        $rawHmac =
            hash_hmac(
                'sha256',
                $signedData,
                $this->webhookKey,
                true
            );

        $expectedBase64 =
            base64_encode(
                $rawHmac
            );

        return hash_equals(
            $expectedBase64,
            $providedSignature
        );
    }

    /**
     * Generate Relworx's documented hex signature.
     */
    public function generateSignature(
        string $timestamp,
        array $params
    ): string {

        return hash_hmac(
            'sha256',
            $this->buildSignedData(
                $timestamp,
                $params
            ),
            $this->webhookKey,
            false
        );
    }

    /**
     * Construct signed data exactly as Relworx documents.
     */
    protected function buildSignedData(
        string $timestamp,
        array $params
    ): string {

        /*
        |--------------------------------------------------------------------------
        | Exact configured callback URL
        |--------------------------------------------------------------------------
        */

        $signedData =
            $this->webhookUrl;

        /*
        |--------------------------------------------------------------------------
        | Append timestamp
        |--------------------------------------------------------------------------
        */

        $signedData .=
            $timestamp;

        /*
        |--------------------------------------------------------------------------
        | Sort alphabetically
        |--------------------------------------------------------------------------
        */

        ksort(
            $params,
            SORT_STRING
        );

        /*
        |--------------------------------------------------------------------------
        | Append key + value WITHOUT delimiters
        |--------------------------------------------------------------------------
        */

        foreach (
            $params as $key => $value
        ) {
            $signedData .=
                (string) $key;

            $signedData .=
                (string) $value;
        }

        return $signedData;
    }

    protected function parseSignatureHeader(
        string $header
    ): array {

        $result = [];

        foreach (
            explode(',', $header) as $part
        ) {

            $pair =
                explode(
                    '=',
                    trim($part),
                    2
                );

            if (
                count($pair) !== 2
            ) {
                continue;
            }

            [$key, $value] =
                $pair;

            $result[
                trim($key)
            ] =
                trim($value);
        }

        return $result;
    }
}
