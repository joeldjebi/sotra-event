<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lignes par défaut d'une compagnie dans les communes d'Abidjan : lieu d'embarquement habituel et bus par offre.
 * Une compagnie qui les a définies accepte d'office les événements qu'on lui confie ; la capacité de chaque
 * événement est calculée à partir de ces lignes (RG-08), puis reste ajustable dans le plan de transport.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('commune_code', 3);
            $table->string('place');
            $table->geography('location', 'point', 4326);
            $table->json('bus_counts');
            $table->timestamps();

            $table->unique(['company_id', 'commune_code']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->json('seats_per_bus')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('seats_per_bus');
        });

        Schema::dropIfExists('company_lines');
    }
};
