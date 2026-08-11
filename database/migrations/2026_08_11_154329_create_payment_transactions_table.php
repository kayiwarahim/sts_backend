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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->constrained('payments')
                ->cascadeOnDelete();

            $table->string('transaction_reference')->unique();

            $table->string('provider_reference')->nullable();

            $table->enum('transaction_type', [
                'collection',
                'refund',
                'reversal'
            ])->default('collection');

            $table->enum('status', [
                'pending',
                'processing',
                'successful',
                'failed',
                'timeout'
            ])->default('pending');

            $table->decimal('amount', 15, 2);

            $table->json('request_data')->nullable();

            $table->json('response_data')->nullable();

            $table->text('error_message')->nullable();

            $table->dateTime('initiated_at');

            $table->dateTime('completed_at')->nullable();

            $table->timestamps();

            $table->index([
                'payment_id',
                'status'
            ]);

            $table->index('provider_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
