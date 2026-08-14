<?php

namespace App\Services;

use App\Models\Property;
use App\Models\WaterWallet;
use Illuminate\Support\Facades\DB;
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
                'property_id' => $property->id,
            ],
            [
                'currency' => 'UGX',
                'balance' => 0,
                'status' => 'active',
            ]
        );
    }

    /**
     * Credit the water wallet.
     */
    public function credit(
        Property $property,
        float $amount
    ): WaterWallet {

        if ($amount <= 0) {
            throw new RuntimeException(
                'Wallet credit amount must be greater than zero.'
            );
        }

        return DB::transaction(function () use (
            $property,
            $amount
        ) {

            $wallet = WaterWallet::query()
                ->where(
                    'property_id',
                    $property->id
                )
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                $wallet = WaterWallet::create([
                    'property_id' => $property->id,
                    'currency' => 'UGX',
                    'balance' => 0,
                    'status' => 'active',
                ]);
            }

            if ($wallet->status !== 'active') {
                throw new RuntimeException(
                    'Water wallet is not active.'
                );
            }

            $wallet->balance =
                (float) $wallet->balance + $amount;

            $wallet->save();

            return $wallet->fresh();
        });
    }

    /**
     * Debit the water wallet.
     */
    public function debit(
        Property $property,
        float $amount
    ): WaterWallet {

        if ($amount <= 0) {
            throw new RuntimeException(
                'Wallet debit amount must be greater than zero.'
            );
        }

        return DB::transaction(function () use (
            $property,
            $amount
        ) {

            $wallet = WaterWallet::query()
                ->where(
                    'property_id',
                    $property->id
                )
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new RuntimeException(
                    'Water wallet does not exist.'
                );
            }

            if ($wallet->status !== 'active') {
                throw new RuntimeException(
                    'Water wallet is not active.'
                );
            }

            if (
                (float) $wallet->balance < $amount
            ) {
                throw new RuntimeException(
                    'Insufficient water wallet balance.'
                );
            }

            $wallet->balance =
                (float) $wallet->balance - $amount;

            $wallet->save();

            return $wallet->fresh();
        });
    }
}