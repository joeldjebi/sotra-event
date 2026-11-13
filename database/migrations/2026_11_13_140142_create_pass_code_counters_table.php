<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteur NNNN du code Pass, par événement et par commune (décision du 13/11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pass_code_counters', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('commune_code', 3);
            $table->unsignedSmallInteger('last_number')->default(0);

            $table->primary(['event_id', 'commune_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_code_counters');
    }
};
