<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class LedgerAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            [
                'code' => 'PAYMENT_CLEARING',
                'name' => 'Payment Clearing',
                'type' => 'asset',
            ],

            [
                'code' => 'WATER_PAYABLE',
                'name' => 'Water Funds Payable',
                'type' => 'liability',
            ],

            [
                'code' => 'VAT_PAYABLE',
                'name' => 'VAT Payable',
                'type' => 'liability',
            ],

            [
                'code' => 'GATEWAY_PAYABLE',
                'name' => 'Gateway Fees Payable',
                'type' => 'liability',
            ],

            [
                'code' => 'LANDLORD_PAYABLE',
                'name' => 'Landlord Payable',
                'type' => 'liability',
            ],

            [
                'code' => 'SERVICE_REVENUE',
                'name' => 'Service Fee Revenue',
                'type' => 'revenue',
            ],

            [
                'code' => 'SAAS_REVENUE',
                'name' => 'SaaS Revenue',
                'type' => 'revenue',
            ],
        ];

        Organization::query()
            ->select('id')
            ->chunkById(100, function ($organizations) use ($accounts) {

                foreach ($organizations as $organization) {

                    foreach ($accounts as $account) {

                        LedgerAccount::updateOrCreate(
                            [
                                'organization_id' => $organization->id,

                                'code' => $account['code'],
                            ],
                            [
                                'name' => $account['name'],

                                'type' => $account['type'],

                                'currency' => 'UGX',

                                'is_active' => true,
                            ]
                        );
                    }
                }
            });
    }
}
