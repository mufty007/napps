<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_cases', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lawyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('debtor_name');
            $table->string('debtor_phone')->nullable();
            $table->unsignedBigInteger('amount_kobo');
            $table->unsignedSmallInteger('days_overdue')->default(90);
            $table->string('stage')->default('demand_sent')->index();
            $table->string('court_name')->nullable();
            $table->string('court_file_number')->nullable();
            $table->text('summary')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_cases');
    }
};
