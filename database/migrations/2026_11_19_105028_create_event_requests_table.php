<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes d'événement d'un promoteur. ACCES EVENT les étudie puis crée l'événement dans la console
 * (compagnie, grille tarifaire et commission restent fixées par la console).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promoter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('venue_name')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('expected_attendance')->nullable();
            $table->unsignedInteger('estimated_passes')->nullable();
            $table->string('desired_communes', 500)->nullable();
            $table->string('status')->index();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['promoter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_requests');
    }
};
