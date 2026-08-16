<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Property;
use App\Models\WaterWallet;
use App\Models\WaterWalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WaterWalletService
{
    /**
     * Get or create the water wallet for a property.
     */
    public function getOrCreateWallet(
        Property $property
    ): WaterWallet {
        return WaterWallet::firstOrCreate(
            [
                'property_id' =>
                    $property->id,
            ],
            [
                'currency' =>
                    'UGX',

                'balance' =>
                    0,

                'status' =>
                    'active',
            ]
        );
    }

    /**
     * Credit the water wallet.
     *
     * If a Payment is supplied, the transaction becomes
     * idempotent for that payment.
     */
    public function credit(
        Property $property,
        float $amount,
        ?Payment $payment = null,
        ?string $description = null
    ): WaterWallet {
        if ($amount <= 0) {
            throw new RuntimeException(
                'Wallet credit amount must be greater than zero.'
            );
        }

        return DB::transaction(
            function () use (
                $property,
                $amount,
                $payment,
                $description
            ) {
                $wallet =
                    WaterWallet::query()
                        ->where(
                            'property_id',
                            $property->id
                        )
                        ->lockForUpdate()
                        ->first();

                /*
                |--------------------------------------------------------------------------
                | Create wallet if missing
                |--------------------------------------------------------------------------
                */

                if (!$wallet) {
                    $wallet =
                        WaterWallet::create([
                            'property_id' =>
                                $property->id,

                            'currency' =>
                                'UGX',

                            'balance' =>
                                0,

                            'status' =>
                                'active',
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Reload with lock
                    |--------------------------------------------------------------------------
                    */

                    $wallet =
                        WaterWallet::query()
                            ->whereKey(
                                $wallet->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();
                }

                /*
                |--------------------------------------------------------------------------
                | Validate wallet status
                |--------------------------------------------------------------------------
                */

                if (
                    $wallet->status !==
                    'active'
                ) {
                    throw new RuntimeException(
                        'Water wallet is not active.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Payment-level idempotency
                |--------------------------------------------------------------------------
                |
                | A successful payment must only credit its water allocation once.
                |--------------------------------------------------------------------------
                */

                if ($payment) {
                    $existing =
                        WaterWalletTransaction::query()
                            ->where(
                                'water_wallet_id',
                                $wallet->id
                            )
                            ->where(
                                'payment_id',
                                $payment->id
                            )
                            ->where(
                                'type',
                                'credit'
                            )
                            ->first();

                    if ($existing) {
                        return $wallet->fresh();
                    }
                }

                $balanceBefore =
                    (float)
                    $wallet->balance;

                $balanceAfter =
                    round(
                        $balanceBefore +
                        $amount,
                        2
                    );

                /*
                |--------------------------------------------------------------------------
                | Update wallet
                |--------------------------------------------------------------------------
                */

                $wallet->balance =
                    $balanceAfter;

                $wallet->save();

                /*
                |--------------------------------------------------------------------------
                | Store transaction
                |--------------------------------------------------------------------------
                */

                WaterWalletTransaction::create([
                    'water_wallet_id' =>
                        $wallet->id,

                    'payment_id' =>
                        $payment?->id,

                    'nwsc_payment_id' =>
                        null,

                    'type' =>
                        'credit',

                    'amount' =>
                        round(
                            $amount,
                            2
                        ),

                    'balance_before' =>
                        $balanceBefore,

                    'balance_after' =>
                        $balanceAfter,

                    'reference' =>
                        $payment
                            ? 'PAYMENT-CREDIT-' .
                                $payment->id
                            : $this
                                ->generateReference(
                                    'CREDIT'
                                ),

                    'description' =>
                        $description
                        ??
                        (
                            $payment
                                ? 'Water allocation credit for payment ' .
                                    $payment->reference
                                : 'Water wallet credit'
                        ),
                ]);

                return $wallet->fresh();
            }
        );
    }

    /**
     * Debit the water wallet.
     */
    public function debit(
        Property $property,
        float $amount,
        ?string $description = null
    ): WaterWallet {
        if ($amount <= 0) {
            throw new RuntimeException(
                'Wallet debit amount must be greater than zero.'
            );
        }

        return DB::transaction(
            function () use (
                $property,
                $amount,
                $description
            ) {
                $wallet =
                    WaterWallet::query()
                        ->where(
                            'property_id',
                            $property->id
                        )
                        ->lockForUpdate()
                        ->first();

                /*
                |--------------------------------------------------------------------------
                | Wallet must exist
                |--------------------------------------------------------------------------
                */

                if (!$wallet) {
                    throw new RuntimeException(
                        'Water wallet does not exist.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Validate wallet status
                |--------------------------------------------------------------------------
                */

                if (
                    $wallet->status !==
                    'active'
                ) {
                    throw new RuntimeException(
                        'Water wallet is not active.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Validate funds
                |--------------------------------------------------------------------------
                */

                if (
                    (float)
                    $wallet->balance <
                    $amount
                ) {
                    throw new RuntimeException(
                        'Insufficient water wallet balance.'
                    );
                }

                $balanceBefore =
                    (float)
                    $wallet->balance;

                $balanceAfter =
                    round(
                        $balanceBefore -
                        $amount,
                        2
                    );

                /*
                |--------------------------------------------------------------------------
                | Update balance
                |--------------------------------------------------------------------------
                */

                $wallet->balance =
                    $balanceAfter;

                $wallet->save();

                /*
                |--------------------------------------------------------------------------
                | Store transaction
                |--------------------------------------------------------------------------
                */

                WaterWalletTransaction::create([
                    'water_wallet_id' =>
                        $wallet->id,

                    'payment_id' =>
                        null,

                    'nwsc_payment_id' =>
                        null,

                    'type' =>
                        'debit',

                    'amount' =>
                        round(
                            $amount,
                            2
                        ),

                    'balance_before' =>
                        $balanceBefore,

                    'balance_after' =>
                        $balanceAfter,

                    'reference' =>
                        $this
                            ->generateReference(
                                'DEBIT'
                            ),

                    'description' =>
                        $description
                        ??
                        'Water wallet debit',
                ]);

                return $wallet->fresh();
            }
        );
    }

    /**
     * Generate unique wallet transaction reference.
     */
    protected function generateReference(
        string $prefix
    ): string {
        do {
            $reference =
                'WW-' .
                strtoupper(
                    $prefix
                ) .
                '-' .
                now()->format(
                    'YmdHis'
                ) .
                '-' .
                strtoupper(
                    Str::random(10)
                );
        } while (
            WaterWalletTransaction::query()
                ->where(
                    'reference',
                    $reference
                )
                ->exists()
        );

        return $reference;
    }
}