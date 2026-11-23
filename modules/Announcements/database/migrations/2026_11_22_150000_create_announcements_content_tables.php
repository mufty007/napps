<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->string('audience')->default('public'); // public | members | parents
            $table->string('level')->default('national'); // national | state | chapter
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('kind')->default('event'); // event | webinar | meeting | agm
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('venue')->nullable();
            $table->string('online_url')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->string('audience')->default('members');
            $table->string('level')->default('national');
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('school_name')->nullable();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();
            $table->string('employment_type')->default('full_time'); // full_time | part_time | contract
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('summary')->nullable();
            $table->longText('body');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chapter_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['chapter_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_parents');
        Schema::dropIfExists('job_listings');
        Schema::dropIfExists('events');
        Schema::dropIfExists('news_posts');
    }
};
