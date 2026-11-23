<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fees and collections (PRD: Fees - structures, invoices, payments, reminders, agreements).
 *
 *   fee_structures / fee_structure_items   a school's price list for a term
 *   fee_invoices                           what one family owes for one child
 *   fee_payments                           money received against an invoice
 *   fee_reminders                          the SMS reminder log (one row per kind sent)
 *   fee_agreements                         a parent's signed fee agreement and consents
 *
 * Amounts are kobo. Records (students) is built later, so invoices carry the
 * child's name and an optional student_id with no foreign key yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('academic_year', 16)->comment('e.g. 2026/2027');
            $table->string('term', 32)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'active']);
        });

        Schema::create('fee_structure_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('amount_kobo');
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('student_id')->nullable()->index()->comment('Records student, once Records exists');
            $table->string('student_name');
            $table->foreignId('guardian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone', 24)->nullable();
            $table->foreignId('fee_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 32)->unique();
            $table->json('lines')->nullable()->comment('Snapshot of the structure items at issue');
            $table->unsignedBigInteger('total_kobo');
            $table->unsignedBigInteger('paid_kobo')->default(0);
            $table->date('due_date');
            $table->string('status', 16)->default('draft')->comment('draft, issued, partial, paid, void, overdue');
            $table->timestamp('issued_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('claim_reference', 32)->nullable()->comment('Debt claim opened for this invoice');
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status', 'due_date']);
            $table->index(['guardian_user_id', 'status']);
        });

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->string('method', 16)->comment('paystack, cash, transfer, teller');
            $table->string('reference', 64)->nullable()->comment('Gateway reference or teller number');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            // One gateway transaction can never be credited twice.
            $table->foreignId('payment_intent_id')->nullable()->unique()->constrained('gateway_transactions')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['fee_invoice_id', 'paid_at']);
        });

        Schema::create('fee_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16)->default('sms');
            $table->timestamp('sent_at');
            $table->string('kind', 8)->comment('before, on, 7, 14, 30');
            $table->timestamps();

            $table->index(['fee_invoice_id', 'kind']);
        });

        Schema::create('fee_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('guardian_user_id')->constrained('users')->restrictOnDelete();
            $table->string('student_name');
            $table->timestamp('signed_at');
            $table->boolean('consent_reminders')->default(false);
            $table->boolean('consent_debtor_check')->default(false);
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'signed_at']);
            $table->index(['guardian_user_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_agreements');
        Schema::dropIfExists('fee_reminders');
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_invoices');
        Schema::dropIfExists('fee_structure_items');
        Schema::dropIfExists('fee_structures');
    }
};
