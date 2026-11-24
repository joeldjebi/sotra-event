<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connexion des applications mobiles par téléphone + mot de passe.
 *
 * Le spectateur choisit le sien après un code SMS (inscription, mot de passe oublié) ; le personnel reçoit
 * un mot de passe généré par l'exploitation, à changer à la première connexion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('password')->nullable()->after('phone');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->string('password')->nullable()->after('phone');
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('password');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['password', 'must_change_password']);
        });
    }
};
