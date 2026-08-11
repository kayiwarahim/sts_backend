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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();

            $table->string('provider');

            $table->string('event_type')->nullable();

            $table->string('event_id')->nullable();

            $table->string('signature')->nullable();

            $table->json('payload');

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
                'provider',
                'event_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
