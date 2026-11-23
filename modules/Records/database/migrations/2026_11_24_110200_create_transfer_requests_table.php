<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            // The school that holds the pupil's record and must answer.
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // The school the pupil is moving to (the one asking).
            $table->foreignId('to_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            // Five working days after the request (PRD: transfer clearance SLA).
            $table->dateTime('sla_due_at');
            $table->text('response_notes')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('responded_at')->nullable();
            $table->unsignedBigInteger('outstanding_kobo')->default(0);
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['to_school_id', 'status']);
            $table->index(['status', 'sla_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_requests');
    }
};
