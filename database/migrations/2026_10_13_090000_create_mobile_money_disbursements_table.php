<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_money_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('water_wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('nwsc_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->string('reference', 36)->unique();
            $table->string('msisdn', 16);
            $table->char('currency', 3)->default('UGX');
            $table->decimal('amount', 15, 2);
            $table->decimal('gateway_fee', 15, 2);
            $table->decimal('total_debit', 15, 2);
            $table->string('status')->default('pending');
            $table->string('description', 255)->nullable();
            $table->string('provider_reference')->nullable()->unique();
            $table->string('provider_transaction_id')->nullable();
            $table->decimal('provider_charge', 15, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->json('provider_response')->nullable();
            $table->foreignId('reservation_ledger_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignId('completion_ledger_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignId('nwsc_payment_id')->nullable()->constrained('nwsc_payments')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_disbursements');
    }
};
