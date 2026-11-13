<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Utilisateurs des espaces web : ACCES EVENT, compagnies, promoteurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->foreignId('company_id')->nullable()->after('phone')->constrained()->restrictOnDelete();
            $table->foreignId('promoter_id')->nullable()->after('company_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('promoter_id');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('promoter_id');
            $table->dropColumn(['phone', 'is_active', 'last_login_at']);
        });
    }
};
