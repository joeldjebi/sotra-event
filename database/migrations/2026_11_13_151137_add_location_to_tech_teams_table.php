<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dernière position connue de l'équipe (distance et temps jusqu'à une panne, ST-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tech_teams', function (Blueprint $table) {
            $table->geography('location', 'point', 4326)->nullable()->after('depot_id');
            $table->timestamp('located_at')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('tech_teams', function (Blueprint $table) {
            $table->dropColumn(['location', 'located_at']);
        });
    }
};
