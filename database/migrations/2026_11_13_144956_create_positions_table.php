<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Positions GPS envoyées par l'app convoyeur pendant une mission, toutes les 15 s (§ 12 CDC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->geography('location', 'point', 4326);
            $table->unsignedSmallInteger('accuracy_m')->nullable();
            $table->decimal('speed_kmh', 5, 1)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['mission_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
