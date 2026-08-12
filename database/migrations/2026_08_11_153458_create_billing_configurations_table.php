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
        Schema::create('billing_configurations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->restrictOnDelete();

            $table->string('name');

            $table->decimal('water_percentage', 5, 2)
                ->default(75.00);

            $table->decimal('service_fee_percentage', 5, 2)
                ->default(5.00);

            $table->decimal('vat_percentage', 5, 2)
                ->default(10.00);

            $table->decimal('gateway_fee_percentage', 5, 2)
                ->default(4.00);

            $table->decimal('landlord_percentage', 5, 2)
                ->default(3.00);

            $table->decimal('saas_percentage', 5, 2)
                ->default(3.00);

            $table->date('effective_from');

            $table->date('effective_to')->nullable();

            $table->string('status')->default('active');

            $table->timestamps();

            $table->index([
                'property_id',
                'status'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_configurations');
    }
};
