<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Équipes techniques mobilisées sur un événement et secteur couvert (ex. « secteur Nord », ST-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_tech_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tech_team_id')->constrained()->cascadeOnDelete();
            $table->string('sector');
            $table->timestamps();

            $table->unique(['event_id', 'tech_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tech_teams');
    }
};
