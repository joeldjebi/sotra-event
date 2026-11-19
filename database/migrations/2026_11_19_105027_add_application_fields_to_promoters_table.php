<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le promoteur devient une organisation : inscription en ligne, dossier (RCCM, justificatifs facultatifs)
 * et validation par ACCES EVENT. Les promoteurs existants, créés par la console, sont validés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promoters', function (Blueprint $table) {
            $table->string('status')->default('valide')->index();
            $table->string('rccm_number', 60)->nullable();
            $table->string('rccm_document_path')->nullable();
            $table->string('id_document_path')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
        });

        DB::table('promoters')->where('is_active', false)->update(['status' => 'suspendu']);
    }

    public function down(): void
    {
        Schema::table('promoters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['status', 'rccm_number', 'rccm_document_path', 'id_document_path', 'applied_at', 'phone_verified_at', 'reviewed_at', 'review_note']);
        });
    }
};
