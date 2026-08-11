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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->date('period_start');

            $table->date('period_end');

            $table->decimal('gross_amount', 15, 2)->default(0);

            $table->decimal('landlord_amount', 15, 2)->default(0);

            $table->decimal('adjustments', 15, 2)->default(0);

            $table->decimal('net_amount', 15, 2)->default(0);

            $table->enum('status', [
                'pending',
                'processing',
                'approved',
                'paid',
                'cancelled'
            ])->default('pending');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('approved_at')->nullable();

            $table->timestamps();

            $table->unique([
                'organization_id',
                'period_start',
                'period_end'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
