<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Models\Scopes\SchoolVisibilityScope;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[ScopedBy([SchoolVisibilityScope::class])]
#[Fillable([
    'state_id', 'chapter_id', 'name', 'slug', 'membership_number', 'registration_number',
    'ministry_approval_number', 'levels', 'address', 'lga', 'latitude', 'longitude',
    'pupil_count', 'phone', 'email', 'membership_status',
])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public const LEVELS = ['nursery', 'primary', 'secondary'];

    protected static function booted(): void
    {
        static::creating(function (School $school): void {
            $school->slug ??= static::uniqueSlug($school->name);
        });

        // The denormalised state_id always follows the chapter.
        static::saving(function (School $school): void {
            if ($school->chapter_id && $school->isDirty('chapter_id')) {
                $school->state_id = Chapter::query()->whereKey($school->chapter_id)->value('state_id');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'levels' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'pupil_count' => 'integer',
            'membership_status' => MembershipStatus::class,
        ];
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'school';
        $slug = $base;
        $i = 2;

        while (static::withoutGlobalScopes()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('org')->logFillable()->logOnlyDirty();
    }
}
