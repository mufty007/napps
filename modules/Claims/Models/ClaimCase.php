<?php

namespace Modules\Claims\Models;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Support\Money as MoneyFormatter;
use App\Support\Scope\ActiveContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Claims\Enums\ClaimStage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A debt claim from demand letter to enforcement. Documents live in media
 * collections (see DOCUMENTS); every change is also written to claim_events.
 *
 * @property ClaimStage $stage
 */
class ClaimCase extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** Media collection => label. Schools may add the first two; the legal team adds any. */
    public const DOCUMENTS = [
        'demand_letter' => 'Demand letter',
        'evidence' => 'Evidence (agreement, invoices, reminders)',
        'pleadings' => 'Court pleadings',
        'judgment' => 'Judgment or order',
    ];

    /** Collections the owning school may add to. */
    public const SCHOOL_DOCUMENTS = ['demand_letter', 'evidence'];

    protected $fillable = [
        'reference', 'school_id', 'opened_by', 'lawyer_id', 'debtor_name', 'debtor_phone',
        'amount_kobo', 'days_overdue', 'stage', 'court_name', 'court_file_number',
        'summary', 'notes', 'escalated_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_kobo' => 'integer',
            'days_overdue' => 'integer',
            'stage' => ClaimStage::class,
            'escalated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $disk = config('napps.claims.documents_disk', config('napps.membership.documents_disk', 'local'));
        $mimes = [
            'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        foreach (array_keys(self::DOCUMENTS) as $collection) {
            $definition = $this->addMediaCollection($collection)->useDisk($disk)->acceptsMimeTypes($mimes);

            if ($collection === 'demand_letter') {
                $definition->singleFile();
            }
        }
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lawyer_id');
    }

    /** @return HasMany<ClaimEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ClaimEvent::class)->latest('created_at')->latest('id');
    }

    /** @return HasMany<ClaimHearing, $this> */
    public function hearings(): HasMany
    {
        return $this->hasMany(ClaimHearing::class)->orderBy('scheduled_at');
    }

    /** @return HasMany<ClaimSettlement, $this> */
    public function settlements(): HasMany
    {
        return $this->hasMany(ClaimSettlement::class)->latest('id');
    }

    /** @return HasMany<ClaimFee, $this> */
    public function fees(): HasMany
    {
        return $this->hasMany(ClaimFee::class)->latest('id');
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('stage', '!=', ClaimStage::Closed->value);
    }

    /** @param  Builder<self>  $query */
    public function scopeClosed(Builder $query): void
    {
        $query->where('stage', ClaimStage::Closed->value);
    }

    /**
     * Case reference, debtor or school name.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(fn (Builder $q) => $q
            ->where('reference', 'like', $like)
            ->orWhere('debtor_name', 'like', $like)
            ->orWhere('debtor_phone', 'like', $like)
            ->orWhereHas('school', fn ($s) => $s->where('name', 'like', $like)));
    }

    /**
     * What the active role may see: the secretariat and national executives see
     * everything, panel lawyers only their assigned files, everyone else the
     * cases of schools inside their State, Chapter or School.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, ActiveContext $context): void
    {
        if ($context->isSystem() || $context->is(Role::Secretariat, Role::NationalExecutive)) {
            return;
        }

        if ($context->is(Role::LegalPanel)) {
            $query->where('lawyer_id', $context->user()?->getKey() ?? 0);

            return;
        }

        $context->constrainBySchoolColumn($query);
    }

    /** Next scheduled hearing (from loaded or lazily loaded hearings). */
    public function nextHearing(): ?ClaimHearing
    {
        return $this->hearings
            ->filter(fn (ClaimHearing $h) => $h->scheduled_at->isFuture())
            ->sortBy('scheduled_at')
            ->first();
    }

    public function amount(): string
    {
        return MoneyFormatter::format($this->amount_kobo);
    }

    public function isClosed(): bool
    {
        return $this->stage === ClaimStage::Closed;
    }
}
