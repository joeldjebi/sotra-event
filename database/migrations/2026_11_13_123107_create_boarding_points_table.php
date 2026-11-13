<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points d'embarquement (AD-02). La zone tarifaire est calculée depuis la distance au lieu (RG-06),
 * avec forçage possible et justifié.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boarding_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('commune');
            $table->string('commune_code', 3);
            $table->string('place');
            $table->geography('location', 'point', 4326);
            $table->decimal('distance_km', 6, 2);
            $table->unsignedTinyInteger('zone');
            $table->boolean('zone_forced')->default(false);
            $table->string('zone_justification')->nullable();
            $table->timestamp('departure_at');
            $table->timestamp('return_departure_at');
            $table->unsignedSmallInteger('travel_minutes')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_points');
    }
};
