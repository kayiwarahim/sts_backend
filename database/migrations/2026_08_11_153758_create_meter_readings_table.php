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
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meter_id')
                ->constrained('meters')
                ->restrictOnDelete();

            $table->decimal('reading', 15, 3);

            $table->decimal('consumption_m3', 15, 3)->nullable();

            $table->string('reading_source')->default('api');

            $table->dateTime('reading_at');

            $table->json('raw_data')->nullable();

            $table->timestamps();

            $table->index([
                'meter_id',
                'reading_at'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
