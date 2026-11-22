<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dues and levies (PRD: Membership dues - year, amount, paid, split).
 *
 *   dues_charges        what a level (national/state/chapter) asks schools to pay
 *   membership_dues     one row per school per issued charge
 *   dues_payments       money received (online or recorded offline), with a receipt
 *   dues_allocations    split ledger: each payment's share per level
 *   membership_suspensions  why and when a school was suspended / reinstated
 *
 * Amounts are kobo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dues_charges', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 16)->comment('national, state or chapter: who set it');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('kind', 16)->comment('annual or levy');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('amount_kobo');
            $table->date('due_date');
            // Percent of each payment kept by each level; sums to 100.
            $table->unsignedTinyInteger('share_national')->default(0);
            $table->unsignedTinyInteger('share_state')->default(0);
            $table->unsignedTinyInteger('share_chapter')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id', 'year']);
            $table->index(['year', 'issued_at']);
        });

        Schema::create('dues_payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 32)->nullable()->unique();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->string('method', 16)->comment('online, bank_transfer, cash, pos');
            // One gateway transaction can never be credited twice.
            $table->foreignId('gateway_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('reference', 64)->nullable()->comment('Gateway reference or teller number');
            $table->timestamp('paid_at');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'paid_at']);
        });

        Schema::create('membership_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('dues_charge_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->date('due_date');
            $table->string('status', 16)->default('unpaid')->comment('unpaid, paid, void');
            $table->foreignId('dues_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'dues_charge_id']);
            $table->index(['status', 'due_date']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('dues_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dues_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_due_id')->constrained()->cascadeOnDelete();
            $table->string('level', 16)->comment('national, state or chapter');
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->timestamps();

            $table->index(['level', 'state_id']);
            $table->index(['level', 'chapter_id']);
        });

        Schema::create('membership_suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->boolean('automatic')->default(false)->comment('Applied by the overdue-dues rule');
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('suspended_at');
            $table->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->text('lift_note')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'lifted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_suspensions');
        Schema::dropIfExists('dues_allocations');
        Schema::dropIfExists('membership_dues');
        Schema::dropIfExists('dues_payments');
        Schema::dropIfExists('dues_charges');
    }
};
