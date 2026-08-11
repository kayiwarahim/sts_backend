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
        Schema::create('nwsc_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nwsc_account_id')
                ->constrained('nwsc_accounts')
                ->restrictOnDelete();

            $table->foreignId('nwsc_bill_id')
                ->nullable()
                ->constrained('nwsc_bills')
                ->nullOnDelete();

            $table->foreignId('water_wallet_id')
                ->constrained('water_wallets')
                ->restrictOnDelete();

            $table->string('reference')->unique();

            $table->decimal('amount', 15, 2);

            $table->string('payment_method');

            $table->enum('status', [
                'pending',
                'processing',
                'successful',
                'failed',
                'cancelled'
            ])->default('pending');

            $table->foreignId('initiated_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('provider_reference')->nullable();

            $table->json('request_data')->nullable();

            $table->json('response_data')->nullable();

            $table->dateTime('paid_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nwsc_payments');
    }
};
