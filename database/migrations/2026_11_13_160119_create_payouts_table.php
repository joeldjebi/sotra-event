<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reversements à la compagnie (RG-14, PA-05, AD-11) : un relevé par événement, calculé, validé puis payé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('event_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('passes_sold');
            $table->unsignedInteger('passes_refunded');
            $table->unsignedBigInteger('gross');
            $table->unsignedBigInteger('refunds');
            $table->unsignedBigInteger('commission');
            $table->unsignedBigInteger('net');
            $table->date('due_on');
            $table->string('status')->index();
            $table->timestamp('calculated_at');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
