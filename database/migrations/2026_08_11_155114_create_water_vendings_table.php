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
        Schema::create('water_vendings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->constrained('payments')
                ->restrictOnDelete();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->restrictOnDelete();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->restrictOnDelete();

            $table->foreignId('meter_id')
                ->constrained('meters')
                ->restrictOnDelete();

            $table->foreignId('water_tariff_id')
                ->constrained('water_tariffs')
                ->restrictOnDelete();

            $table->decimal('amount', 15, 2);

            $table->decimal('price_per_m3', 15, 2);

            $table->decimal('volume_m3', 15, 3);

            $table->string('reference')->unique();

            $table->enum('status', [
                'pending',
                'processing',
                'successful',
                'failed',
                'cancelled',
            ])->default('pending');

            $table->dateTime('vended_at')->nullable();

            $table->timestamps();

            $table->index([
                'meter_id',
                'status',
            ]);

            $table->index([
                'tenant_id',
                'created_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('water_vendings');
    }
};
