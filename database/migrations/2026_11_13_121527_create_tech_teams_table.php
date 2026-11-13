<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Équipes du service technique (ex. T1, T3) : dépannage léger ou lourd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tech_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('type');
            $table->string('status');
            $table->foreignId('depot_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tech_teams');
    }
};
