<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantPortalUserSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('email', 'devkrahim@gmail.com')->firstOrFail();

        $user = User::updateOrCreate(
            [
                'email' => 'devkrahim@gmail.com',
            ],
            [
                'organization_id' => $tenant->organization_id,
                'name' => $tenant->first_name.' '.$tenant->last_name,
                'password' => Hash::make('Tenant@12345'),
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles(['Tenant']);
        $tenant->update(['user_id' => $user->id,
        ]);
    }
}
