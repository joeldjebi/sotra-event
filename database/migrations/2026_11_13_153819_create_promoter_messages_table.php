<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages du promoteur aux détenteurs de Pass (PR-04, PR-08) : ciblage, canaux, nombre de destinataires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promoter_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audience');
            $table->string('audience_value')->nullable();
            $table->string('audience_label');
            $table->text('body');
            $table->jsonb('channels');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->string('status');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promoter_messages');
    }
};
