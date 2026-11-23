<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Community, second pass: join requests and invitations for groups, forum
 * replies with upvotes / follows / best answers, chat read positions, and
 * reports for moderators (COM-2, COM-5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_space_members', function (Blueprint $table) {
            // active | pending (asked to join) | invited (waiting for them to accept)
            $table->string('status', 16)->default('active')->after('role');
            $table->index(['user_id', 'status']);
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->foreignId('pinned_by')->nullable()->after('pinned')->constrained('users')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable()->after('hidden_at')->index();
        });

        Schema::table('community_comments', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('best_answer');
        });

        Schema::table('community_threads', function (Blueprint $table) {
            $table->timestamp('answered_at')->nullable()->after('best_comment_id');
            $table->timestamp('hidden_at')->nullable()->after('locked_at');
            $table->timestamp('last_activity_at')->nullable()->after('hidden_at')->index();
        });

        Schema::create('community_thread_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('community_threads')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->integer('votes_count')->default(0);
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'id']);
        });

        Schema::create('community_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_reply_id')->constrained('community_thread_replies')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'thread_reply_id']);
        });

        Schema::create('community_thread_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->constrained('community_threads')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'thread_id']);
        });

        Schema::table('community_conversation_members', function (Blueprint $table) {
            $table->unsignedBigInteger('last_read_message_id')->nullable()->after('last_read_at');
        });

        Schema::table('community_messages', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('body');
        });

        Schema::create('community_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reportable_type');
            $table->unsignedBigInteger('reportable_id');
            // Space the content sits in (null for direct messages); routes the report to that level's moderators.
            $table->foreignId('space_id')->nullable()->constrained('community_spaces')->nullOnDelete();
            $table->string('reason', 32);
            $table->text('details')->nullable();
            $table->string('status', 16)->default('open'); // open | actioned | dismissed
            $table->string('action', 32)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['reportable_type', 'reportable_id']);
            $table->index(['status', 'space_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_reports');
        Schema::table('community_messages', fn (Blueprint $table) => $table->dropColumn('hidden_at'));
        Schema::table('community_conversation_members', fn (Blueprint $table) => $table->dropColumn('last_read_message_id'));
        Schema::dropIfExists('community_thread_follows');
        Schema::dropIfExists('community_votes');
        Schema::dropIfExists('community_thread_replies');
        Schema::table('community_threads', fn (Blueprint $table) => $table->dropColumn(['answered_at', 'hidden_at', 'last_activity_at']));
        Schema::table('community_comments', fn (Blueprint $table) => $table->dropColumn('hidden_at'));
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinned_by');
            $table->dropColumn('last_activity_at');
        });
        Schema::table('community_space_members', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
