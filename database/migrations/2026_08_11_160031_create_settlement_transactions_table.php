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
        Schema::create('settlement_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('settlement_id')
                ->constrained('settlements')
                ->cascadeOnDelete();

            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete();

            $table->enum('type', [
                'landlord_share',
                'adjustment',
                'deduction',
                'refund'
            ]);

            $table->decimal('amount', 15, 2);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index([
                'settlement_id',
                'type'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_transactions');
    }
};
