<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Normalize existing inconsistent data
        |--------------------------------------------------------------------------
        */

        DB::table('tenancies')
            ->where(
                'status',
                'active'
            )
            ->update([
                'end_date' => null,
            ]);

        DB::table(
            'meter_assignments'
        )
            ->where(
                'status',
                'active'
            )
            ->update([
                'unassigned_at' => null,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Performance indexes
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'tenancies',
            function (
                Blueprint $table
            ) {
                $table->index(
                    [
                        'unit_id',
                        'status',
                    ],
                    'tenancy_unit_status_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'status',
                    ],
                    'tenancy_tenant_status_idx'
                );
            }
        );

        Schema::table(
            'meter_assignments',
            function (
                Blueprint $table
            ) {
                $table->index(
                    [
                        'unit_id',
                        'status',
                    ],
                    'meter_assign_unit_status_idx'
                );

                $table->index(
                    [
                        'meter_id',
                        'status',
                    ],
                    'meter_assign_meter_status_idx'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Date integrity
        |--------------------------------------------------------------------------
        */

        DB::statement('
            ALTER TABLE tenancies
            ADD CONSTRAINT chk_tenancy_date_order
            CHECK (
                end_date IS NULL
                OR end_date >= start_date
            )
        ');

        DB::statement('
            ALTER TABLE meter_assignments
            ADD CONSTRAINT chk_meter_assignment_date_order
            CHECK (
                unassigned_at IS NULL
                OR unassigned_at >= assigned_at
            )
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE tenancies
            DROP CONSTRAINT chk_tenancy_date_order
        ');

        DB::statement('
            ALTER TABLE meter_assignments
            DROP CONSTRAINT chk_meter_assignment_date_order
        ');

        Schema::table(
            'tenancies',
            function (
                Blueprint $table
            ) {
                $table->dropIndex(
                    'tenancy_unit_status_idx'
                );

                $table->dropIndex(
                    'tenancy_tenant_status_idx'
                );
            }
        );

        Schema::table(
            'meter_assignments',
            function (
                Blueprint $table
            ) {
                $table->dropIndex(
                    'meter_assign_unit_status_idx'
                );

                $table->dropIndex(
                    'meter_assign_meter_status_idx'
                );
            }
        );
    }
};
