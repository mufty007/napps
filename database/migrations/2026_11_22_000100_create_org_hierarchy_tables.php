<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The association's org chain: State -> Chapter -> School.
 *
 * Schools carry a denormalised state_id so state-level scoping is a single
 * indexed lookup instead of a join through chapters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 3)->unique();
            $table->timestamps();
        });

        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('lga')->nullable();
            $table->timestamps();

            $table->unique(['state_id', 'name']);
        });

        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->restrictOnDelete();
            $table->foreignId('chapter_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('membership_number', 32)->nullable()->unique();
            $table->string('registration_number', 64)->nullable()->comment('CAC registration');
            $table->string('ministry_approval_number', 64)->nullable();
            $table->json('levels')->nullable()->comment('nursery, primary, secondary');
            $table->string('address')->nullable();
            $table->string('lga')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('pupil_count')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('membership_status', 16)->default('pending')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['state_id', 'membership_status']);
            $table->index(['chapter_id', 'membership_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('states');
    }
};
