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

        // $landlord = User::updateOrCreate([
        //     'name' => 'Landlord',
        //     'email' => 'rahimkayiwa@gmail.com',
        //     'password' => bcrypt('Landlord@1234'),
        // ]);
        // $landlord->assignRole('Landlord');
        $admin = User::updateOrCreate([
            'name' => 'Super Admin',
            'email' => 'kayiwarahim@gmail.com',
            'password' => bcrypt('Admin@1234'),
        ]);
        $admin->assignRole('Super Admin');
    }


}
