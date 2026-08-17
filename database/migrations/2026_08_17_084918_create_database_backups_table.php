<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();

            $table->string('reference')
                ->unique();

            $table->string('filename');

            $table->string('disk')
                ->default('local');

            $table->string('path');

            $table->string('database_name');

            $table->enum('type', [
                'manual',
                'scheduled',
                'pre_restore',
            ])->default('manual');

            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'restoring',
                'restored',
            ])->default('pending');

            $table->unsignedBigInteger('size_bytes')
                ->nullable();

            $table->string('checksum', 64)
                ->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('restored_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('started_at')
                ->nullable();

            $table->dateTime('completed_at')
                ->nullable();

            $table->dateTime('restored_at')
                ->nullable();

            $table->text('error_message')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'type',
                'status',
            ]);

            $table->index(
                'created_at'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'database_backups'
        );
    }
};