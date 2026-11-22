<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user can hold several roles, each anchored to a place in the org chain
 * (e.g. proprietor of school 12 AND chapter executive of chapter 3).
 * Spatie roles define *what* a role may do; this table defines *where*.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('scope_type', 16);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_id'], 'role_assignments_unique');
            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('purpose', 24);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('role_assignments');
    }
};
