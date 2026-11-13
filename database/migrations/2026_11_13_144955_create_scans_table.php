<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scans des Pass par les convoyeurs (CV-06 à CV-10), en ligne ou synchronisés après coup (NF-09).
 * client_uuid (généré par l'app) rend l'envoi idempotent : un scan renvoyé deux fois n'est enregistré qu'une fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('pass_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('segment');
            $table->string('input', 512);
            $table->string('result');
            $table->string('reason')->nullable();
            $table->boolean('offline')->default(false);
            $table->boolean('conflict')->default(false);
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['pass_id', 'segment', 'result']);
            $table->index(['mission_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
