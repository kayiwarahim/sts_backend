<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliation_records', function (Blueprint $table) {

            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->nullOnDelete();

            $table->string('reconciliation_type')
                ->default('payment')
                ->after('organization_id');

            $table->index([
                'organization_id',
                'status',
            ]);

            $table->index(
                [
                    'reconciliation_type',
                    'transaction_date',
                ],
                'recon_type_date_idx'
            );

            $table->unique([
                'provider',
                'internal_reference',
            ], 'reconciliation_provider_internal_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_records', function (Blueprint $table) {

            $table->dropUnique(
                'reconciliation_provider_internal_unique'
            );

            $table->dropIndex([
                'organization_id',
                'status',
            ]);

            $table->dropIndex([
                'reconciliation_type',
                'transaction_date',
            ]);

            $table->dropForeign([
                'organization_id'
            ]);

            $table->dropColumn([
                'organization_id',
                'reconciliation_type',
            ]);
        });
    }
};