<?php

namespace Database\Seeders;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(DefaultOrganizationSeeder::class);
        $this->call(LedgerAccountSeeder::class);
        $this->call(RelworxPaymentProviderSeeder::class);
        $this->call(TenantPortalUserSeeder::class);

        $landlord = User::factory()->create([
            'name' => 'Landlord',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
        $landlord->assignRole('Landlord');

        $admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('Super Admin');
    }


}
