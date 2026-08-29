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
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->constrained('payments')
                ->cascadeOnDelete();

            $table->foreignId('billing_configuration_id')
                ->constrained('billing_configurations')
                ->restrictOnDelete();

            $table->enum('allocation_type', [
                'water',
                'service_fee',
                'vat',
                'gateway_fee',
                'landlord',
                'saas',
            ]);

            $table->decimal('percentage', 5, 2);

            $table->decimal('amount', 15, 2);

            $table->timestamps();

            $table->index([
                'payment_id',
                'allocation_type',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
