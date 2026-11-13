<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des Pass offerts (RG-04) : chaque transfert invalide l'ancien QR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pass_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('to_customer_id')->constrained('customers')->restrictOnDelete();
            $table->unsignedSmallInteger('previous_token_version');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pass_transfers');
    }
};
