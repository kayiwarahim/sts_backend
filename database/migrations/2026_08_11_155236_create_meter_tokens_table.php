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
        Schema::create('meter_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('water_vending_id')
                ->constrained('water_vendings')
                ->restrictOnDelete();

            $table->foreignId('meter_id')
                ->constrained('meters')
                ->restrictOnDelete();

            $table->foreignId('sts_transaction_id')
                ->nullable()
                ->constrained('sts_transactions')
                ->nullOnDelete();

            $table->text('token');

            $table->string('token_sequence')->nullable();

            $table->string('token_type')->nullable();

            $table->decimal('volume_m3', 15, 3)->nullable();

            $table->enum('status', [
                'generated',
                'issued',
                'used',
                'failed',
                'cancelled'
            ])->default('generated');

            $table->dateTime('generated_at');

            $table->dateTime('issued_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meter_tokens');
    }
};
