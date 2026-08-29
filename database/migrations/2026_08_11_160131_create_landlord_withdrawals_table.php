<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('landlord_withdrawals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->foreignId('landlord_wallet_id')
                ->constrained('landlord_wallets')
                ->restrictOnDelete();

            $table->foreignId('settlement_id')
                ->nullable()
                ->constrained('settlements')
                ->nullOnDelete();

            $table->string('reference')->unique();

            $table->decimal('amount', 15, 2);

            $table->enum('method', [
                'mobile_money',
                'bank',
            ]);

            $table->string('account_number');

            $table->string('account_name')->nullable();

            $table->enum('status', [
                'pending',
                'processing',
                'successful',
                'failed',
                'cancelled',
            ])->default('pending');

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('processed_at')->nullable();

            $table->string('provider_reference')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landlord_withdrawals');
    }
};
