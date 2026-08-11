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
        Schema::create('water_tariffs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->restrictOnDelete();

            $table->string('name');

            $table->decimal('price_per_m3', 15, 2);

            $table->date('effective_from');

            $table->date('effective_to')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index([
                'property_id',
                'is_active'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('water_tariffs');
    }
};
