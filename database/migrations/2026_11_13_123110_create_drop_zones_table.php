<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zones de dépose, de reprise et d'accueil sur le lieu (AD-04, PR-05).
 * Le périmètre est un cercle (centre + rayon) : un bus y entrant est « arrivé » (§ 9 CDC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drop_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('usage');
            $table->timestamp('scheduled_at')->nullable();
            $table->geography('location', 'point', 4326);
            $table->unsignedSmallInteger('radius_m')->default(150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drop_zones');
    }
};
