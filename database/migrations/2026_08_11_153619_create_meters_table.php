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
        Schema::create('meters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->string('meter_number')->unique();

            $table->string('serial_number')
                ->nullable()
                ->unique();

            $table->string('manufacturer')->nullable();

            $table->string('model')->nullable();

            $table->string('meter_type')->default('2');

            $table->string('key_revision')->nullable();

            $table->string('supply_group_code')->nullable();

            $table->enum('status', [
                'active',
                'inactive',
                'faulty',
                'tampered',
                'decommissioned'
            ])->default('active');

            $table->date('installed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meters');
    }
};
