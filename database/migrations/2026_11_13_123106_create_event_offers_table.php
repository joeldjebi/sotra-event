<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offres actives d'un événement et capacité définie par la compagnie (RG-08) :
 * capacité de l'offre = nombre de bus × places par bus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('offer');
            $table->unsignedSmallInteger('bus_count')->nullable();
            $table->unsignedSmallInteger('seats_per_bus')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'offer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_offers');
    }
};
