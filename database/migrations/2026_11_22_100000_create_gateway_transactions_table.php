<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every online payment attempt, whichever module started it. Amounts in kobo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->string('gateway', 16);
            $table->string('purpose', 48)->index()->comment('Module flow, e.g. membership.dues');
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 16)->default('pending');
            $table->string('channel', 32)->nullable();
            $table->string('gateway_response')->nullable();
            $table->string('authorization_url', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_transactions');
    }
};
