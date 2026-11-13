<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche compagnie complète (AD-08) : contrat, adresse, compte de reversement (chiffré).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('contract_reference')->nullable()->after('legal_name');
            $table->date('contract_starts_on')->nullable()->after('contract_reference');
            $table->string('address')->nullable()->after('phone');
            $table->string('payout_method')->nullable()->after('default_commission_rate');
            $table->text('payout_details')->nullable()->after('payout_method');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['legal_name', 'contract_reference', 'contract_starts_on', 'address', 'payout_method', 'payout_details']);
        });
    }
};
