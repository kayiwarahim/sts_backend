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
        Schema::create('reconciliation_records', function (Blueprint $table) {
            $table->id();

            $table->string('provider');

            $table->string('provider_reference')->nullable();

            $table->string('internal_reference')->nullable();

            $table->dateTime('transaction_date');

            $table->decimal('expected_amount', 15, 2);

            $table->decimal('actual_amount', 15, 2);

            $table->decimal('difference', 15, 2)->default(0);

            $table->enum('status', [
                'matched',
                'unmatched',
                'partial',
                'resolved'
            ])->default('unmatched');

            $table->json('external_data')->nullable();

            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('resolved_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index([
                'provider',
                'provider_reference'
            ]);

            $table->index([
                'status',
                'transaction_date'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_records');
    }
};
