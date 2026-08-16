<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_configurations', function (Blueprint $table) {

            if (!Schema::hasColumn(
                'billing_configurations',
                'water_tariff_id'
            )) {
                $table->foreignId('water_tariff_id')
                    ->nullable()
                    ->after('property_id')
                    ->constrained('water_tariffs')
                    ->restrictOnDelete();
            }

        });
    }

    public function down(): void
    {
        Schema::table('billing_configurations', function (Blueprint $table) {

            if (Schema::hasColumn(
                'billing_configurations',
                'water_tariff_id'
            )) {
                $table->dropForeign([
                    'water_tariff_id'
                ]);

                $table->dropColumn(
                    'water_tariff_id'
                );
            }

        });
    }
};