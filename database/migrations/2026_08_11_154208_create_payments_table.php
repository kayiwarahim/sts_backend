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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->restrictOnDelete();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->restrictOnDelete();

            $table->foreignId('payment_provider_id')
                ->constrained('payment_providers')
                ->restrictOnDelete();

            $table->foreignId('payment_provider_account_id')
                ->nullable()
                ->constrained('payment_provider_accounts')
                ->nullOnDelete();

            $table->unsignedBigInteger('ledger_transaction_id')
                ->nullable();

            $table->string('reference')->unique();

            $table->decimal('amount', 15, 2);

            $table->char('currency', 3)->default('UGX');

            $table->string('payer_phone')->nullable();

            $table->enum('status', [
                'pending',
                'processing',
                'successful',
                'failed',
                'cancelled',
                'refunded',
                'reversed',
            ])->default('pending');

            $table->dateTime('initiated_at');

            $table->dateTime('completed_at')->nullable();

            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index('ledger_transaction_id');

            $table->index([
                'organization_id',
                'property_id',
            ]);

            $table->index([
                'tenant_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
