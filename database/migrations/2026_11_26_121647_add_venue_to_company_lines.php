<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lignes par défaut : plusieurs lieux d'embarquement par commune, chacun vers une destination (un lieu
 * d'événement, ou tous les lieux si vide), avec ses bus par offre. Le point d'embarquement d'un événement
 * garde la ligne dont il est issu : la capacité de l'événement se calcule à partir de ces lignes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_lines', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'commune_code']);
            $table->foreignId('venue_id')->nullable()->after('commune_code')->constrained()->cascadeOnDelete();
            $table->index(['company_id', 'commune_code']);
        });

        Schema::table('boarding_points', function (Blueprint $table) {
            $table->foreignId('company_line_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
        });

        // Points déjà repris des lignes (une seule ligne par commune jusqu'ici) : rattachement à leur ligne.
        DB::statement(<<<'SQL'
            update boarding_points
            set company_line_id = company_lines.id
            from events, company_lines
            where events.id = boarding_points.event_id
              and company_lines.company_id = events.company_id
              and company_lines.commune_code = boarding_points.commune_code
              and company_lines.place = boarding_points.place
        SQL);
    }

    public function down(): void
    {
        Schema::table('boarding_points', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_line_id');
        });

        Schema::table('company_lines', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'commune_code']);
            $table->dropConstrainedForeignId('venue_id');
            $table->unique(['company_id', 'commune_code']);
        });
    }
};
