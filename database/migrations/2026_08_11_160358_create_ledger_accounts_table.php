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
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->string('code');

            $table->string('name');

            $table->enum('type', [
                'asset',
                'liability',
                'revenue',
                'expense',
                'equity'
            ]);

            $table->char('currency', 3)->default('UGX');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique([
                'organization_id',
                'code'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
