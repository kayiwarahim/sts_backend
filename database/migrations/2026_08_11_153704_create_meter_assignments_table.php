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
        Schema::create('meter_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meter_id')
                ->constrained('meters')
                ->restrictOnDelete();

            $table->foreignId('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            $table->dateTime('assigned_at');

            $table->dateTime('unassigned_at')->nullable();

            $table->enum('status', [
                'active',
                'ended',
            ])->default('active');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index([
                'meter_id',
                'status',
            ]);

            $table->index([
                'unit_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meter_assignments');
    }
};
