<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDA et smartphones utilisés par les convoyeurs et techniciens (inventaire, attribution, blocage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('serial_number')->unique();
            $table->string('label');
            $table->string('model')->nullable();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('status');
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_reason')->nullable();
            $table->string('app_version')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
