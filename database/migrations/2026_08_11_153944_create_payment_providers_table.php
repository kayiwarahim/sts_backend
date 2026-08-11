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
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->string('code')->unique();

            $table->enum('type', [
                'mobile_money',
                'bank',
                'card',
                'aggregator'
            ]);

            $table->text('base_url')->nullable();

            $table->boolean('is_active')->default(true);

            $table->json('configuration')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_providers');
    }
};
