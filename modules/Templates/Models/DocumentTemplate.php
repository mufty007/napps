<?php

namespace Modules\Templates\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A versioned, legally reviewed document with {{placeholders}} that members
 * fill in and export as a PDF. Editing the body bumps the version and clears
 * the legal review (see TemplateLibraryController::update).
 *
 * @property array<string, array{label: string, type: string}>|null $placeholders
 */
class DocumentTemplate extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const CATEGORIES = ['legal', 'membership', 'fees', 'records', 'leases', 'general'];

    protected $fillable = [
        'slug', 'name', 'description', 'category', 'body_html', 'placeholders', 'version',
        'legally_reviewed_at', 'reviewed_by', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'version' => 'integer',
            'legally_reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return array<string, string> category value => label */
    public static function categoryLabels(): array
    {
        return [
            'legal' => __('Legal'),
            'membership' => __('Membership'),
            'fees' => __('Fees'),
            'records' => __('Records'),
            'leases' => __('Leases'),
            'general' => __('General'),
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @param  Builder<self>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** @param  Builder<self>  $query */
    public function scopeReviewed(Builder $query): void
    {
        $query->whereNotNull('legally_reviewed_at');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    public function isReviewed(): bool
    {
        return $this->legally_reviewed_at !== null;
    }

    public function categoryLabel(): string
    {
        return self::categoryLabels()[$this->category] ?? ucfirst($this->category);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('templates')
            ->logOnly(['slug', 'name', 'category', 'version', 'legally_reviewed_at', 'reviewed_by', 'published_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
