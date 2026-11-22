<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-1/MEM-2: a school applies, the chapter verifies, the state approves.
 *
 * The school row is created (status "pending") when the application is
 * submitted, so the usual org scopes route it to the right chapter and
 * state. A rejected application soft-deletes its school.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('applicant_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('submitted')->index();
            $table->timestamp('submitted_at');

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_stage', 16)->nullable()->comment('chapter or state');
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index(['applicant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_applications');
    }
};
