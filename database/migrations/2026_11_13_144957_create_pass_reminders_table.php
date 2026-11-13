<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rappels envoyés au détenteur d'un Pass (SP-21 : J-1 et H-2), un seul de chaque sorte par Pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pass_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['pass_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_reminders');
    }
};
