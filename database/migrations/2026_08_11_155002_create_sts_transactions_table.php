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
        Schema::create('sts_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meter_id')
                ->constrained('meters')
                ->restrictOnDelete();

            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete();

            $table->string('reference')->unique();

            $table->enum('transaction_type', [
                'token_generation',
                'token_reissue',
                'clear_tamper',
                'meter_reading',
                'meter_configuration',
                'other'
            ]);

            $table->string('external_reference')->nullable();

            $table->enum('status', [
                'pending',
                'processing',
                'successful',
                'failed'
            ])->default('pending');

            $table->decimal('amount', 15, 2)->nullable();

            $table->decimal('volume_m3', 15, 3)->nullable();

            $table->text('token')->nullable();

            $table->json('request_data')->nullable();

            $table->json('response_data')->nullable();

            $table->text('error_message')->nullable();

            $table->dateTime('completed_at')->nullable();

            $table->timestamps();

            $table->index([
                'meter_id',
                'transaction_type'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sts_transactions');
    }
};
