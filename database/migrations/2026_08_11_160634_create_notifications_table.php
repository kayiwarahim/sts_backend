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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('channel', [
                'sms',
                'email',
                'push',
                'system',
            ]);

            $table->string('type');

            $table->string('title');

            $table->text('message');

            $table->json('data')->nullable();

            $table->dateTime('sent_at')->nullable();

            $table->dateTime('read_at')->nullable();

            $table->enum('status', [
                'pending',
                'sent',
                'failed',
            ])->default('pending');

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
