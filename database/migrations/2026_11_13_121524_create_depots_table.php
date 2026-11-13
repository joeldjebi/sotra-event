<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dépôts des compagnies (point d'attache des bus et des bus de réserve).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->geography('location', 'point', 4326)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depots');
    }
};
