<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polls in posts (quick polls) and formal votes (COM-4): one ballot per
 * member school, locked at close, exportable for the minutes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained('community_posts')->cascadeOnDelete();
            $table->boolean('multiple')->default(false);
            $table->boolean('anonymous')->default(false);
            $table->boolean('formal')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('community_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('community_polls')->cascadeOnDelete();
            $table->string('label', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('community_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('community_polls')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('community_poll_options')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Set only on formal votes: the member school this ballot is cast for.
            $table->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['poll_id', 'option_id', 'user_id']);
            $table->unique(['poll_id', 'school_id']); // one ballot per school (NULLs never collide)
            $table->index(['poll_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_poll_votes');
        Schema::dropIfExists('community_poll_options');
        Schema::dropIfExists('community_polls');
    }
};
