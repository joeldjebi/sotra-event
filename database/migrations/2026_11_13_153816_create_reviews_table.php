<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avis des spectateurs après le trajet (SP-22) : une note sur 5 par critère, un avis par Pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('mission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('punctuality');
            $table->unsignedTinyInteger('comfort');
            $table->unsignedTinyInteger('courtesy');
            $table->unsignedTinyInteger('driving');
            $table->unsignedTinyInteger('information');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
