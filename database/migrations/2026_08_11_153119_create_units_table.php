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
        Schema::create('units', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained('properties')
                ->cascadeOnDelete();

            $table->string('unit_number');

            $table->string('floor')->nullable();

            $table->string('description')->nullable();

            $table->enum('status', [
                'occupied',
                'vacant',
                'maintenance',
                'inactive',
            ])->default('vacant');

            $table->timestamps();

            $table->unique([
                'property_id',
                'unit_number',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
