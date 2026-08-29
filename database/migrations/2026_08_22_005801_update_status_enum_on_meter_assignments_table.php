<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Temporarily Support Both Old and New Values
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE meter_assignments
            MODIFY COLUMN status
            ENUM('active', 'inactive', 'ended')
            NOT NULL
            DEFAULT 'active'
        ");

        /*
        |--------------------------------------------------------------------------
        | Convert Old Historical Assignments
        |--------------------------------------------------------------------------
        */

        DB::table('meter_assignments')
            ->where('status', 'inactive')
            ->update([
                'status' => 'ended',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Final Assignment Statuses
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE meter_assignments
            MODIFY COLUMN status
            ENUM('active', 'ended')
            NOT NULL
            DEFAULT 'active'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE meter_assignments
            MODIFY COLUMN status
            ENUM('active', 'inactive', 'ended')
            NOT NULL
            DEFAULT 'active'
        ");

        DB::table('meter_assignments')
            ->where('status', 'ended')
            ->update([
                'status' => 'inactive',
            ]);

        DB::statement("
            ALTER TABLE meter_assignments
            MODIFY COLUMN status
            ENUM('active', 'inactive')
            NOT NULL
            DEFAULT 'active'
        ");
    }
};
