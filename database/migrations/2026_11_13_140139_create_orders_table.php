<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commandes de Pass. Une commande = un point, une offre, un type, N Pass.
 * Les taux (frais, commission) sont figés sur la commande (RG-15) ; montants en FCFA entiers (RG-16).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('boarding_point_id')->constrained()->restrictOnDelete();
            $table->string('offer');
            $table->string('pass_type');
            $table->unsignedSmallInteger('quantity');
            $table->string('beneficiary_phone')->nullable();
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('subtotal');
            $table->decimal('fee_rate', 5, 2);
            $table->unsignedInteger('fee_amount');
            $table->decimal('commission_rate', 5, 2);
            $table->unsignedInteger('commission_amount');
            $table->unsignedInteger('company_amount');
            $table->unsignedInteger('total');
            $table->string('channel');
            $table->string('status')->index();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
