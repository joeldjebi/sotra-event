<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mission aller affectée au Pass (liste « Attendus », CV-12) ; la mission retour est fixée au scan (RG-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            $table->foreignId('outbound_mission_id')->nullable()->after('holder_id')->constrained('missions')->nullOnDelete();
            $table->foreignId('return_mission_id')->nullable()->after('outbound_mission_id')->constrained('missions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('outbound_mission_id');
            $table->dropConstrainedForeignId('return_mission_id');
        });
    }
};
