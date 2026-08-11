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
        Schema::create('water_wallets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->unique()
                ->constrained('properties')
                ->restrictOnDelete();

            $table->char('currency', 3)->default('UGX');

            $table->decimal('balance', 15, 2)->default(0);

            $table->enum('status', [
                'active',
                'frozen',
                'closed'
            ])->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('water_wallets');
    }
};
