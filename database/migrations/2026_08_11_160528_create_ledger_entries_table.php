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
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ledger_transaction_id')
                ->constrained('ledger_transactions')
                ->cascadeOnDelete();

            $table->foreignId('ledger_account_id')
                ->constrained('ledger_accounts')
                ->restrictOnDelete();

            $table->decimal('debit', 15, 2)->default(0);

            $table->decimal('credit', 15, 2)->default(0);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index([
                'ledger_transaction_id',
                'ledger_account_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
