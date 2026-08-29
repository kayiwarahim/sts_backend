<?php

namespace Database\Seeders;

use App\Models\PaymentProvider;
use App\Models\PaymentProviderAccount;
use Illuminate\Database\Seeder;

class RelworxPaymentProviderSeeder extends Seeder
{
    public function run(): void
    {
        $provider = PaymentProvider::updateOrCreate(
            [
                'code' => 'RELWORX',
            ],
            [
                'name' => 'Relworx',
                'type' => 'aggregator',
                'base_url' => 'https://payments.relworx.com/api',
                'is_active' => true,
                'configuration' => [
                    'supports' => [
                        'MTN_UGANDA',
                        'AIRTEL_UGANDA',
                    ],
                ],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Global Relworx account
        |--------------------------------------------------------------------------
        |
        | The bearer token stays in .env.
        | We only store the account number here.
        |
        */

        PaymentProviderAccount::updateOrCreate(
            [
                'payment_provider_id' => $provider->id,
                'organization_id' => null,
                'name' => 'Default Relworx Account',
            ],
            [
                'merchant_code' => config('services.relworx.account_no'),

                'credentials' => null,

                'environment' => 'production',

                'is_active' => true,
            ]
        );
    }
}
