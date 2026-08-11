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
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_provider_id')
                ->constrained('payment_providers')
                ->restrictOnDelete();

            $table->string('event_id')->nullable();

            $table->string('provider_reference')->nullable();

            $table->string('event_type')->nullable();

            $table->json('payload');

            $table->string('signature')->nullable();

            $table->enum('status', [
                'received',
                'processing',
                'processed',
                'failed',
                'ignored'
            ])->default('received');

            $table->text('error_message')->nullable();

            $table->dateTime('processed_at')->nullable();

            $table->timestamps();

            $table->index([
                'payment_provider_id',
                'event_id'
            ]);

            $table->index('provider_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};
