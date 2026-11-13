<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Échanges entre ACCES EVENT et la compagnie sur un événement proposé (CO-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_negotiations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('side');
            $table->string('subject');
            $table->text('message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_negotiations');
    }
};
