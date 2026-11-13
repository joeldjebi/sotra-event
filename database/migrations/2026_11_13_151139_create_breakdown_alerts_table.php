<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alertes de panne signalées par les convoyeurs (CV-15 à CV-19, ST-02 à ST-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breakdown_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->constrained('staff')->restrictOnDelete();
            $table->string('type');
            $table->text('details')->nullable();
            $table->geography('location', 'point', 4326)->nullable();
            $table->unsignedSmallInteger('accuracy_m')->nullable();
            $table->string('place_label')->nullable();
            $table->unsignedSmallInteger('passengers_on_board');
            $table->string('urgency');
            $table->string('status')->index();
            $table->string('channel');
            $table->string('mission_status_before');
            $table->jsonb('photos')->default('[]');
            $table->foreignId('tech_team_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('taken_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breakdown_alerts');
    }
};
