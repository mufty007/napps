<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // Set when the person also has a platform account at this school.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('role_title', 100);
            $table->string('phone', 20)->nullable();
            $table->date('started_at')->nullable();
            $table->date('left_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'left_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_members');
    }
};
