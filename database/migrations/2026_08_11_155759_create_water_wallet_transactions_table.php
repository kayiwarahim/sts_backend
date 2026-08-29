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
        Schema::create('water_wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('water_wallet_id')
                ->constrained('water_wallets')
                ->restrictOnDelete();

            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete();

            $table->foreignId('nwsc_payment_id')
                ->nullable()
                ->constrained('nwsc_payments')
                ->nullOnDelete();

            $table->enum('type', [
                'credit',
                'debit',
                'adjustment',
                'refund',
            ]);

            $table->decimal('amount', 15, 2);

            $table->decimal('balance_before', 15, 2);

            $table->decimal('balance_after', 15, 2);

            $table->string('reference')->unique();

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index([
                'water_wallet_id',
                'created_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('water_wallet_transactions');
    }
};
