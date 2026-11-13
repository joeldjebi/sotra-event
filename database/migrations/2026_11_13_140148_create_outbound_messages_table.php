<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages WhatsApp et notifications push émis (pilotes simulés en attendant les fournisseurs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->string('recipient');
            $table->string('template');
            $table->text('body');
            $table->jsonb('meta')->nullable();
            $table->string('driver');
            $table->string('status');
            $table->timestamps();

            $table->index(['recipient', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
