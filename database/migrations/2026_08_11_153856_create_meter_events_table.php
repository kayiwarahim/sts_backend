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
        Schema::create('meter_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meter_id')
                ->constrained('meters')
                ->restrictOnDelete();

            $table->string('event_type');

            $table->string('event_code')->nullable();

            $table->text('description')->nullable();

            $table->json('data')->nullable();

            $table->enum('status', [
                'open',
                'resolved',
                'ignored',
            ])->default('open');

            $table->dateTime('occurred_at');

            $table->dateTime('resolved_at')->nullable();

            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'meter_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meter_events');
    }
};
