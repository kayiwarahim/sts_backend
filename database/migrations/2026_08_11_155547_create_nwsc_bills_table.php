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
        Schema::create('nwsc_bills', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nwsc_account_id')
                ->constrained('nwsc_accounts')
                ->restrictOnDelete();

            $table->string('bill_number')->nullable();

            $table->date('billing_period');

            $table->decimal('amount', 15, 2);

            $table->date('due_date')->nullable();

            $table->decimal('balance', 15, 2);

            $table->enum('status', [
                'unpaid',
                'partially_paid',
                'paid',
                'overdue',
                'cancelled'
            ])->default('unpaid');

            $table->json('raw_data')->nullable();

            $table->timestamps();

            $table->index([
                'nwsc_account_id',
                'billing_period'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nwsc_bills');
    }
};
