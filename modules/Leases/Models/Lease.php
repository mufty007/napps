<?php

namespace Modules\Leases\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Leases\Enums\LeaseStatus;
use Modules\Leases\Enums\RentFrequency;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A school's premises lease. The signed lease, receipts and correspondence
 * live in the "documents" media collection (the vault).
 *
 * @property LeaseStatus $status
 * @property RentFrequency $rent_frequency
 * @property Carbon $start_date
 * @property Carbon $end_date
 */
class Lease extends Model implements HasMedia
{
    use BelongsToSchool, InteractsWithMedia, LogsActivity;

    public const VAULT = 'documents';

    /** Days before expiry at which renewal reminders go out. */
    public const REMINDER_DAYS = [90, 60, 30];

    protected $fillable = [
        'school_id', 'landlord_name', 'landlord_phone', 'property_address', 'start_date', 'end_date',
        'rent_kobo', 'rent_frequency', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_kobo' => 'integer',
            'rent_frequency' => RentFrequency::class,
            'status' => LeaseStatus::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $disk = config('napps.leases.documents_disk', config('napps.membership.documents_disk', 'local'));

        $this->addMediaCollection(self::VAULT)->useDisk($disk)->acceptsMimeTypes([
            'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('leases')
            ->logOnly(['school_id', 'landlord_name', 'landlord_phone', 'property_address', 'start_date', 'end_date', 'rent_kobo', 'rent_frequency', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return HasMany<LeaseRentPayment, $this> */
    public function rentPayments(): HasMany
    {
        return $this->hasMany(LeaseRentPayment::class)->orderByDesc('paid_at')->orderByDesc('id');
    }

    /** @return HasMany<LeaseDispute, $this> */
    public function disputes(): HasMany
    {
        return $this->hasMany(LeaseDispute::class)->latest('id');
    }

    /** @param  Builder<self>  $query */
    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', [LeaseStatus::Active->value, LeaseStatus::Disputed->value]);
    }

    /**
     * Landlord, address or school name.
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
            ->where('landlord_name', 'like', $like)
            ->orWhere('property_address', 'like', $like)
            ->orWhereHas('school', fn ($s) => $s->where('name', 'like', $like)));
    }

    /**
     * Whole days from today until the lease ends (negative once it has ended).
     */
    public function daysToExpiry(): int
    {
        return (int) today()->diffInDays($this->end_date->copy()->startOfDay(), false);
    }

    public function hasEnded(): bool
    {
        return $this->daysToExpiry() < 0;
    }

    /** Ending within the first reminder window and still in force. */
    public function isExpiringSoon(): bool
    {
        $days = $this->daysToExpiry();

        return $days >= 0 && $days <= max(self::REMINDER_DAYS) && in_array($this->status, [LeaseStatus::Active, LeaseStatus::Disputed], true);
    }

    /** Rent over a full year, whatever the payment frequency. */
    public function annualRentKobo(): int
    {
        return $this->rent_kobo * $this->rent_frequency->paymentsPerYear();
    }

    public function rent(): string
    {
        return Money::format($this->rent_kobo);
    }

    public function hasOpenDispute(): bool
    {
        return $this->disputes()->whereNot('status', 'resolved')->exists();
    }
}
