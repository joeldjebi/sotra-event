<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le code ET-AAMM-NNNN-COM ne contient ni l'événement ni sa compagnie : la séquence NNNN doit donc être
 * propre au couple (mois AAMM, commune) pour que deux événements du même mois ne produisent jamais le même code.
 * Les compteurs repartent du plus grand numéro déjà émis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::drop('pass_code_counters');

        Schema::create('pass_code_counters', function (Blueprint $table) {
            $table->char('period', 4);
            $table->string('commune_code', 3);
            $table->unsignedSmallInteger('last_number')->default(0);

            $table->primary(['period', 'commune_code']);
        });

        DB::statement(<<<'SQL'
            INSERT INTO pass_code_counters (period, commune_code, last_number)
            SELECT substr(code, 4, 4), substr(code, 14, 3), max(substr(code, 9, 4)::int)
            FROM passes
            WHERE code IS NOT NULL
            GROUP BY substr(code, 4, 4), substr(code, 14, 3)
        SQL);
    }

    public function down(): void
    {
        Schema::drop('pass_code_counters');

        Schema::create('pass_code_counters', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('commune_code', 3);
            $table->unsignedSmallInteger('last_number')->default(0);

            $table->primary(['event_id', 'commune_code']);
        });
    }
};
