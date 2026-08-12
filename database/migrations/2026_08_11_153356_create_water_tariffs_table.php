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

            $table->string('status')->default('active');

            $table->string('notes', 255)->nullable();

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
        Schema::dropIfExists('water_tariffs');
    }
};
