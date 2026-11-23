<?php

namespace Modules\Announcements\Models;

use App\Models\Chapter;
use App\Models\School;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class JobListing extends Model
{
    protected $fillable = [
        'title', 'slug', 'school_name', 'school_id', 'state_id', 'chapter_id',
        'location', 'employment_type', 'contact_email', 'contact_phone',
        'summary', 'body', 'author_id', 'published_at', 'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $job) {
            if (blank($job->slug)) {
                $job->slug = Str::slug(Str::limit($job->title, 60, '')).'-'.Str::lower(Str::random(4));
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<State, $this> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('closes_at')->orWhere('closes_at', '>=', now()));
    }

    public function employerName(): string
    {
        return $this->school->name ?? $this->school_name ?? config('napps.name');
    }

    public function employmentTypeLabel(): string
    {
        return match ($this->employment_type) {
            'part_time' => __('Part-time'),
            'contract' => __('Contract'),
            default => __('Full-time'),
        };
    }
}
