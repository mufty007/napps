<?php

namespace Modules\Records\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Records\Enums\StudentStatus;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A pupil on a school's register. Results, ID and other papers are media
 * collections (see DOCUMENTS). Changes are audit-logged; "deleting" a pupil
 * only archives the record (soft delete) so history survives.
 *
 * @property StudentStatus $status
 */
#[Fillable([
    'school_id', 'admission_no', 'first_name', 'last_name', 'class_name', 'guardian_user_id',
    'guardian_name', 'guardian_phone', 'status', 'enrolled_at', 'exited_at',
    'debtor_check_consent', 'outstanding_kobo',
])]
class Student extends Model implements HasMedia
{
    use BelongsToSchool, InteractsWithMedia, LogsActivity, SoftDeletes;

    /** Media collection => label. */
    public const DOCUMENTS = [
        'results' => 'Results and report cards',
        'id' => 'ID and birth certificate',
        'other' => 'Other documents',
    ];

    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
            'enrolled_at' => 'date',
            'exited_at' => 'date',
            'debtor_check_consent' => 'boolean',
            'outstanding_kobo' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $disk = config('napps.records.documents_disk', config('napps.membership.documents_disk', 'local'));
        $mimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

        foreach (array_keys(self::DOCUMENTS) as $collection) {
            $this->addMediaCollection($collection)->useDisk($disk)->acceptsMimeTypes($mimes);
        }
    }

    /** @return BelongsTo<User, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    /** @return HasMany<TransferRequest, $this> */
    public function transferRequests(): HasMany
    {
        return $this->hasMany(TransferRequest::class)->latest('id');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function outstanding(): string
    {
        return Money::format($this->outstanding_kobo);
    }

    public function isActive(): bool
    {
        return $this->status === StudentStatus::Active;
    }

    /**
     * Name, admission number, class or guardian.
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
            ->where('first_name', 'like', $like)
            ->orWhere('last_name', 'like', $like)
            ->orWhere('admission_no', 'like', $like)
            ->orWhere('class_name', 'like', $like)
            ->orWhere('guardian_name', 'like', $like)
            ->orWhere('guardian_phone', 'like', $like));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('records')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
