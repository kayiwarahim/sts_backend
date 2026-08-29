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
        Schema::create('payment_provider_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_provider_id')
                ->constrained('payment_providers')
                ->restrictOnDelete();

            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->string('name');

            $table->string('merchant_code')->nullable();

            $table->text('credentials')->nullable();

            $table->enum('environment', [
                'sandbox',
                'production',
            ])->default('sandbox');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(
                [
                    'payment_provider_id',
                    'organization_id',
                ],
                'ppa_provider_org_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_provider_accounts');
    }
};
