<?php

namespace Modules\Announcements\Models;

use App\Models\Chapter;
use App\Models\State;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $fillable = [
        'title', 'slug', 'kind', 'summary', 'body', 'venue', 'online_url',
        'starts_at', 'ends_at', 'audience', 'level', 'state_id', 'chapter_id',
        'author_id', 'published_at', 'agenda', 'minutes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'agenda' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $event) {
            if (blank($event->slug)) {
                $event->slug = Str::slug(Str::limit($event->title, 60, '')).'-'.Str::lower(Str::random(4));
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
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

    /** @return HasMany<EventRsvp, $this> */
    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    /** @return HasMany<EventCheckin, $this> */
    public function checkins(): HasMany
    {
        return $this->hasMany(EventCheckin::class);
    }

    /** @param  Builder<self>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** @param  Builder<self>  $query */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', now()->subHour());
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'webinar' => __('Webinar'),
            'meeting' => __('Meeting'),
            'agm' => __('AGM'),
            default => __('Event'),
        };
    }

    public function isOnline(): bool
    {
        return filled($this->online_url);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    /** When the event is over (events with no end time run for four hours). */
    public function endsAtOrDefault(): CarbonInterface
    {
        return $this->ends_at ?? $this->starts_at->copy()->addHours(4);
    }

    public function hasEnded(): bool
    {
        return $this->endsAtOrDefault()->isPast();
    }

    /** Check-in opens three hours before the start. */
    public function checkInOpensAt(): CarbonInterface
    {
        return $this->starts_at->copy()->subHours(3);
    }

    /** ...and closes three hours after the end. */
    public function checkInClosesAt(): CarbonInterface
    {
        return $this->endsAtOrDefault()->copy()->addHours(3);
    }

    public function checkInIsOpen(): bool
    {
        return now()->between($this->checkInOpensAt(), $this->checkInClosesAt());
    }

    /**
     * Agenda rows as stored: list of ["time" => "09:00", "title" => "Opening"].
     *
     * @return list<array{time: string, title: string}>
     */
    public function agendaItems(): array
    {
        return collect((array) $this->agenda)
            ->filter(fn ($row) => is_array($row) && filled($row['title'] ?? null))
            ->map(fn (array $row) => ['time' => (string) ($row['time'] ?? ''), 'title' => (string) $row['title']])
            ->values()
            ->all();
    }

    /** The agenda as the one-line-per-item text the form edits ("09:00 | Opening prayer"). */
    public function agendaText(): string
    {
        return collect($this->agendaItems())
            ->map(fn (array $item) => $item['time'] !== '' ? $item['time'].' | '.$item['title'] : $item['title'])
            ->implode("\n");
    }

    /**
     * Parse the agenda textarea: one item per line, optional "time | title".
     *
     * @return list<array{time: string, title: string}>|null
     */
    public static function parseAgenda(?string $text): ?array
    {
        $items = collect(preg_split('/\R/', (string) $text) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->take(40)
            ->map(function (string $line) {
                if (str_contains($line, '|')) {
                    [$time, $title] = array_map('trim', explode('|', $line, 2));

                    return ['time' => mb_substr($time, 0, 20), 'title' => mb_substr($title, 0, 200)];
                }

                return ['time' => '', 'title' => mb_substr($line, 0, 200)];
            })
            ->filter(fn (array $item) => $item['title'] !== '')
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }
}
