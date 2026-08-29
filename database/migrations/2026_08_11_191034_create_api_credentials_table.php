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
        Schema::create('api_credentials', function (Blueprint $table) {
            $table->id();

            $table->string('service');

            $table->string('name');

            $table->text('base_url')->nullable();

            $table->text('client_id')->nullable();

            $table->text('client_secret')->nullable();

            $table->text('api_key')->nullable();

            $table->text('username')->nullable();

            $table->text('password')->nullable();

            $table->json('additional_config')->nullable();

            $table->boolean('is_active')->default(true);

            $table->dateTime('last_tested_at')->nullable();

            $table->timestamps();

            $table->index([
                'service',
                'is_active',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_credentials');
    }
};
