<?php

namespace Modules\Membership\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Membership\Enums\ApplicationStatus;
use Modules\Membership\Policies\MembershipApplicationPolicy;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * MEM-1: a proprietor's application to join NAPPS. Executives reach it
 * through the school scope; the applicant (who holds no role yet) reaches
 * it only through the explicit "my applications" path.
 *
 * @property ApplicationStatus $status
 */
#[UsePolicy(MembershipApplicationPolicy::class)]
#[Fillable([
    'school_id', 'applicant_id', 'status', 'submitted_at',
    'verified_by', 'verified_at', 'verification_note',
    'approved_by', 'approved_at',
    'rejected_by', 'rejected_at', 'rejected_stage', 'rejection_reason',
])]
class MembershipApplication extends Model implements HasMedia
{
    use BelongsToSchool, InteractsWithMedia, LogsActivity;

    /** Document collections => [label, required]. */
    public const DOCUMENTS = [
        'cac_certificate' => ['CAC registration certificate', true],
        'ministry_approval' => ['State ministry approval letter', false],
        'premises_photo' => ['Photo of the school premises', false],
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        foreach (array_keys(self::DOCUMENTS) as $collection) {
            $this->addMediaCollection($collection)
                ->singleFile()
                ->useDisk(config('napps.membership.documents_disk', 'local'))
                ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
        }
    }

    /**
     * The applying school, including one soft-deleted after rejection.
     * The application row is already scoped, so the school is loaded as-is.
     *
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Reference people can quote on the phone, e.g. APP-000042.
     */
    public function reference(): string
    {
        return 'APP-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('membership')
            ->logOnly(['status', 'verified_by', 'approved_by', 'rejected_by', 'rejected_stage', 'rejection_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
