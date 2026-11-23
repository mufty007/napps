<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_hearings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_case_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at')->index();
            $table->string('venue')->nullable();
            $table->text('outcome')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_hearings');
    }
};
