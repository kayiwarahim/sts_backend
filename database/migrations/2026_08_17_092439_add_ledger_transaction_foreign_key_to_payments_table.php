<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign(
                'ledger_transaction_id',
                'payments_ledger_tx_fk'
            )
                ->references('id')
                ->on('ledger_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(
                'payments_ledger_tx_fk'
            );
        });
    }
};
