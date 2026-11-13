<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Active PostGIS : positions des bus, périmètres des zones de dépose, distances (zones tarifaires).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS postgis');
    }
};
