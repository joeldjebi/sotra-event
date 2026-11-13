<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bus de réserve d'un événement et leur position d'attente (ST-05, RG-20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_reserve_buses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_id')->constrained()->cascadeOnDelete();
            $table->string('waiting_place');
            $table->timestamps();

            $table->unique(['event_id', 'bus_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reserve_buses');
    }
};
