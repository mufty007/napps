<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('admission_no', 40)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('class_name', 60)->nullable();
            $table->foreignId('guardian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guardian_name', 150);
            // E.164 (+234...). The debtor check matches on this column.
            $table->string('guardian_phone', 20)->nullable()->index();
            $table->string('status', 20)->default('active');
            $table->date('enrolled_at');
            $table->date('exited_at')->nullable();
            // The guardian agreed (at enrolment) that other member schools may ask
            // whether fees are outstanding. Without it the pupil never matches.
            $table->boolean('debtor_check_consent')->default(false);
            // Fees still owed, in kobo. Kept by hand until the Fees module's invoices
            // take over (see App\Contracts\StudentFeeBalances).
            $table->unsignedBigInteger('outstanding_kobo')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'admission_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
