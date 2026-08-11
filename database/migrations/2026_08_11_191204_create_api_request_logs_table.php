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
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();

            $table->string('service');

            $table->string('operation');

            $table->string('method');

            $table->text('url')->nullable();

            $table->string('request_reference')->nullable();

            $table->unsignedSmallInteger('http_status')->nullable();

            $table->json('request_data')->nullable();

            $table->json('response_data')->nullable();

            $table->unsignedInteger('response_time_ms')->nullable();

            $table->enum('status', [
                'success',
                'failed',
                'timeout'
            ])->default('success');

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index([
                'service',
                'operation'
            ]);

            $table->index('request_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
