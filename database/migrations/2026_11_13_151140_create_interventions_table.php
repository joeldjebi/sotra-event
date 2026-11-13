<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interventions du service technique (ST-08 à ST-16) : étapes horodatées, diagnostic, résultat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('breakdown_alert_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->foreignId('tech_team_id')->constrained()->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('step');
            $table->timestamp('taken_at');
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('on_site_at')->nullable();
            $table->timestamp('diagnosis_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('result')->nullable();
            $table->boolean('towing_requested')->default(false);
            $table->text('diagnosis')->nullable();
            $table->string('parts')->nullable();
            $table->unsignedSmallInteger('duration_min')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('photos')->default('[]');
            $table->foreignId('replacement_bus_id')->nullable()->constrained('buses')->nullOnDelete();
            $table->foreignId('replacement_mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
