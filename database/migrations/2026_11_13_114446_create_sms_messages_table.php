<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des SMS émis (tous pilotes confondus). Sert aussi de boîte d'envoi en développement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('to');
            $table->text('body');
            $table->string('driver');
            $table->string('status');
            $table->string('provider_reference')->nullable();
            $table->timestamps();

            $table->index(['to', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
