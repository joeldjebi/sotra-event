<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pass transport (RG-01 à RG-05). Le code (ET-AAMM-NNNN-COM) et le jeton signé sont émis au paiement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('boarding_point_id')->constrained()->restrictOnDelete();
            $table->foreignId('holder_id')->constrained('customers')->restrictOnDelete();
            $table->string('offer');
            $table->string('pass_type');
            $table->string('code')->nullable()->unique();
            $table->unsignedSmallInteger('token_version')->default(1);
            $table->string('status')->index();
            $table->unsignedInteger('price');
            $table->unsignedInteger('fee_amount');
            $table->unsignedInteger('commission_amount');
            $table->unsignedInteger('company_amount');
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'offer', 'status']);
            $table->index(['holder_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passes');
    }
};
