<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_spaces', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // chapter | state | national | interest | committee
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('privacy')->default('open'); // open | request | invite
            $table->text('description')->nullable();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('community_space_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('community_spaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // member | admin | moderator
            $table->timestamps();
            $table->unique(['space_id', 'user_id']);
        });

        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('community_spaces')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('text'); // text | question | poll | event | showcase
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
            $table->index(['space_id', 'created_at']);
        });

        Schema::create('community_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('community_comments')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('best_answer')->default(false);
            $table->timestamps();
        });

        Schema::create('community_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reactable_type');
            $table->unsignedBigInteger('reactable_id');
            $table->string('type')->default('like');
            $table->timestamps();
            $table->unique(['user_id', 'reactable_type', 'reactable_id', 'type'], 'community_reactions_unique');
            $table->index(['reactable_type', 'reactable_id']);
        });

        Schema::create('community_forums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('community_spaces')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['space_id', 'slug']);
        });

        Schema::create('community_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_id')->constrained('community_forums')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_question')->default(false);
            $table->foreignId('best_comment_id')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->index(['forum_id', 'updated_at']);
        });

        Schema::create('community_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('direct'); // direct | group | channel
            $table->string('title')->nullable();
            $table->foreignId('space_id')->nullable()->constrained('community_spaces')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('community_conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('community_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id'], 'community_conversation_members_unique');
        });

        Schema::create('community_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('community_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_messages');
        Schema::dropIfExists('community_conversation_members');
        Schema::dropIfExists('community_conversations');
        Schema::dropIfExists('community_threads');
        Schema::dropIfExists('community_forums');
        Schema::dropIfExists('community_reactions');
        Schema::dropIfExists('community_comments');
        Schema::dropIfExists('community_posts');
        Schema::dropIfExists('community_space_members');
        Schema::dropIfExists('community_spaces');
    }
};
