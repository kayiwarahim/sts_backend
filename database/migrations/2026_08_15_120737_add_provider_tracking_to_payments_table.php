<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            $table->string('provider_reference')
                ->nullable()
                ->unique()
                ->after('reference');

            $table->string('mobile_money_provider')
                ->nullable()
                ->after('payer_phone');

            $table->string('provider_transaction_id')
                ->nullable()
                ->after('mobile_money_provider');

            $table->decimal('provider_charge', 15, 2)
                ->nullable()
                ->after('provider_transaction_id');

            $table->json('provider_response')
                ->nullable()
                ->after('provider_charge');

            $table->index([
                'provider_reference',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {

            $table->dropIndex([
                'provider_reference',
                'status',
            ]);

            $table->dropColumn([
                'provider_reference',
                'mobile_money_provider',
                'provider_transaction_id',
                'provider_charge',
                'provider_response',
            ]);
        });
    }
};