<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Événements (AD-01) : informations, taux, canaux, statut du cycle de vie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('promoter_id')->constrained()->restrictOnDelete();
            $table->foreignId('transport_lead_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('doors_open_at')->nullable();
            $table->unsignedInteger('estimated_passes')->nullable();
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('fee_rate', 5, 2);
            $table->jsonb('channels')->default('[]');
            $table->string('status')->index();
            $table->string('visual_path')->nullable();
            $table->boolean('featured')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('proposed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('sale_opened_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
