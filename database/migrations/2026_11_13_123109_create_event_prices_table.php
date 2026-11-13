<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grille tarifaire d'un événement (AD-03, RG-07) : zone × offre × type de Pass, en FCFA hors frais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('zone');
            $table->string('offer');
            $table->string('pass_type');
            $table->unsignedInteger('price');
            $table->timestamps();

            $table->unique(['event_id', 'zone', 'offer', 'pass_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_prices');
    }
};
