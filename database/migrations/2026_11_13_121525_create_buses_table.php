<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flotte des compagnies (§ 13 CDC : numéro, immatriculation, capacité, statut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('depot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number');
            $table->string('plate')->unique();
            $table->unsignedSmallInteger('capacity');
            $table->string('category');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buses');
    }
};
