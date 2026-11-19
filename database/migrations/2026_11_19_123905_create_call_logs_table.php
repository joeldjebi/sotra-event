<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des appels du centre d'appel ACCES TRANS : qui a appelé, pourquoi, ce qui a été fait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pass_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['phone', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
