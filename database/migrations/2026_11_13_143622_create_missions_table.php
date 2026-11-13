<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Missions (§ 13 CDC) : un bus, pour un point d'embarquement, une offre et un sens (aller ou retour).
 * Le nombre de missions par point et par offre découle des ventes (RG-08) ; l'exploitation y affecte
 * bus, chauffeur, convoyeur et terminal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('boarding_point_id')->constrained()->restrictOnDelete();
            $table->string('offer');
            $table->string('direction');
            $table->unsignedSmallInteger('sequence');
            $table->unsignedSmallInteger('seats');
            $table->foreignId('bus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('conveyor_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('planned_departure_at');
            $table->timestamp('actual_departure_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->string('status');
            $table->foreignId('replaced_by_mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'boarding_point_id', 'offer', 'direction', 'sequence']);
            $table->index(['company_id', 'status']);
            $table->index(['conveyor_id', 'planned_departure_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
