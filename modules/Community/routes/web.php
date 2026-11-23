<?php

use Illuminate\Support\Facades\Route;
use Modules\Community\Http\Controllers\ChatController;
use Modules\Community\Http\Controllers\FeedController;
use Modules\Community\Http\Controllers\ForumController;
use Modules\Community\Http\Controllers\GroupController;
use Modules\Community\Http\Controllers\ModerationController;
use Modules\Community\Http\Controllers\PollController;

Route::middleware(['member', 'can:community.access'])
    ->prefix('community')
    ->name('community.')
    ->group(function () {
        // Feed (COM-1)
        Route::get('/', [FeedController::class, 'index'])->name('index');
        Route::get('/feed', [FeedController::class, 'index'])->name('feed');
        Route::post('/feed', [FeedController::class, 'store'])->middleware('throttle:30,1')->name('feed.store');
        Route::get('/posts/{post}', [FeedController::class, 'show'])->name('posts.show');
        Route::delete('/posts/{post}', [FeedController::class, 'destroy'])->name('posts.destroy');
        Route::post('/posts/{post}/pin', [FeedController::class, 'pin'])->name('posts.pin');
        Route::get('/posts/{post}/comments', [FeedController::class, 'comments'])->name('posts.comments.index');
        Route::post('/posts/{post}/comments', [FeedController::class, 'comment'])->middleware('throttle:60,1')->name('posts.comments');
        Route::post('/posts/{post}/react', [FeedController::class, 'react'])->middleware('throttle:120,1')->name('posts.react');
        Route::delete('/comments/{comment}', [FeedController::class, 'destroyComment'])->name('comments.destroy');

        // Polls and formal votes (COM-4)
        Route::post('/polls/{poll}/vote', [PollController::class, 'vote'])->middleware('throttle:30,1')->name('polls.vote');
        Route::post('/polls/{poll}/close', [PollController::class, 'close'])->name('polls.close');
        Route::get('/polls/{poll}/export', [PollController::class, 'export'])->middleware('throttle:20,1')->name('polls.export');

        // Forums (COM-2)
        Route::get('/forums', [ForumController::class, 'index'])->name('forums.index');
        Route::get('/forums/{forum}', [ForumController::class, 'show'])->name('forums.show');
        Route::post('/forums/{forum}/threads', [ForumController::class, 'storeThread'])->middleware('throttle:20,1')->name('forums.threads.store');
        Route::get('/forums/{forum}/threads/{thread}', [ForumController::class, 'showThread'])->name('forums.threads.show');
        Route::post('/forums/{forum}/threads/{thread}/replies', [ForumController::class, 'reply'])->middleware('throttle:30,1')->name('forums.threads.reply');
        Route::post('/forums/{forum}/threads/{thread}/follow', [ForumController::class, 'follow'])->name('forums.threads.follow');
        Route::post('/forums/{forum}/threads/{thread}/lock', [ForumController::class, 'lock'])->name('forums.threads.lock');
        Route::post('/forums/{forum}/threads/{thread}/replies/{reply}/vote', [ForumController::class, 'vote'])->middleware('throttle:120,1')->name('forums.replies.vote');
        Route::post('/forums/{forum}/threads/{thread}/replies/{reply}/best', [ForumController::class, 'markBest'])->name('forums.replies.best');

        // Groups
        Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
        Route::post('/groups', [GroupController::class, 'store'])->middleware('throttle:10,1')->name('groups.store');
        Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
        Route::post('/groups/{group}/join', [GroupController::class, 'join'])->name('groups.join');
        Route::post('/groups/{group}/accept', [GroupController::class, 'acceptInvite'])->name('groups.accept');
        Route::post('/groups/{group}/leave', [GroupController::class, 'leave'])->name('groups.leave');
        Route::post('/groups/{group}/invite', [GroupController::class, 'invite'])->middleware('throttle:30,1')->name('groups.invite');
        Route::post('/groups/{group}/members/{member}/respond', [GroupController::class, 'respond'])->name('groups.respond');
        Route::post('/groups/{group}/members/{member}/admin', [GroupController::class, 'makeAdmin'])->name('groups.admin');
        Route::delete('/groups/{group}/members/{member}', [GroupController::class, 'removeMember'])->name('groups.remove');

        // Chat (COM-3)
        Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
        Route::post('/chat/direct', [ChatController::class, 'startDirect'])->middleware('throttle:20,1')->name('chat.direct');
        Route::post('/chat/chapter-channel', [ChatController::class, 'ensureChapterChannel'])->name('chat.chapter');
        Route::get('/chat/channels/{space}', [ChatController::class, 'channel'])->name('chat.channel');
        Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
        Route::get('/chat/{conversation}/messages', [ChatController::class, 'messages'])->middleware('throttle:120,1')->name('chat.poll');
        Route::post('/chat/{conversation}', [ChatController::class, 'storeMessage'])->middleware('throttle:60,1')->name('chat.messages');
        Route::get('/chat/{conversation}/messages/{message}/photo', [ChatController::class, 'photo'])->middleware('throttle:240,1')->name('chat.photo');

        // Moderation (COM-5)
        Route::post('/reports', [ModerationController::class, 'report'])->middleware('throttle:20,1')->name('reports.store');
        Route::middleware('can:community.moderate')->group(function () {
            Route::get('/moderation', [ModerationController::class, 'index'])->name('moderation.index');
            Route::post('/moderation/{report}', [ModerationController::class, 'decide'])->name('moderation.decide');
        });
    });
