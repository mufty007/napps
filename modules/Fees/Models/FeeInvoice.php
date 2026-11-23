<?php

namespace Modules\Fees\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Fees\Enums\InvoiceStatus;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * What one family owes a school for one child. The parent portal reaches it
 * through guardian_user_id (parents are personal-scope, so the school scope
 * would hide it); staff reach it through the school scope.
 *
 * @property InvoiceStatus $status
 * @property list<array{name: string, amount_kobo: int}>|null $lines
 */
class FeeInvoice extends Model
{
    use BelongsToSchool, LogsActivity;

    /** More than this many days past due, an invoice can become a debt claim. */
    public const ESCALATION_DAYS = 90;

    protected $fillable = [
        'school_id', 'student_id', 'student_name', 'guardian_user_id', 'guardian_name', 'guardian_phone',
        'fee_structure_id', 'reference', 'lines', 'total_kobo', 'paid_kobo', 'due_date', 'status',
        'issued_at', 'notes', 'claim_reference', 'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'total_kobo' => 'integer',
            'paid_kobo' => 'integer',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FeeStructure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class, 'fee_structure_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    /**
     * @return HasMany<FeePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class)->orderByDesc('paid_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<FeeReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(FeeReminder::class)->orderByDesc('sent_at')->orderByDesc('id');
    }

    /**
     * Unpaid, part paid or overdue: still expecting money.
     *
     * @param  Builder<FeeInvoice>  $query
     */
    #[Scope]
    protected function unpaid(Builder $query): void
    {
        $query->whereIn('status', array_map(fn (InvoiceStatus $s) => $s->value, InvoiceStatus::open()));
    }

    /**
     * Open and past its due date. Uses the date, not the stored status, so it
     * is right even before the nightly job has run.
     *
     * @param  Builder<FeeInvoice>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereIn('status', array_map(fn (InvoiceStatus $s) => $s->value, InvoiceStatus::open()))
            ->where('due_date', '<', today()->toDateString());
    }

    /**
     * @param  Builder<FeeInvoice>  $query
     */
    #[Scope]
    protected function settled(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Paid->value);
    }

    /**
     * @param  Builder<FeeInvoice>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        $query->where(function (Builder $q) use ($like) {
            $q->where('student_name', 'like', $like)
                ->orWhere('reference', 'like', $like)
                ->orWhere('guardian_name', 'like', $like)
                ->orWhere('guardian_phone', 'like', $like);
        });
    }

    public function balanceKobo(): int
    {
        return max(0, $this->total_kobo - $this->paid_kobo);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isEscalated(): bool
    {
        return $this->claim_reference !== null;
    }

    /**
     * Whole days past the due date while money is still owed (0 otherwise).
     */
    public function daysOverdue(): int
    {
        if (! $this->isOpen() || $this->balanceKobo() <= 0 || ! $this->due_date->lt(today())) {
            return 0;
        }

        return (int) $this->due_date->diffInDays(today());
    }

    /**
     * What to show people: an open invoice past its due date is overdue even
     * before the nightly job flips the stored status.
     */
    public function displayStatus(): InvoiceStatus
    {
        return $this->isOpen() && $this->due_date->lt(today()) ? InvoiceStatus::Overdue : $this->status;
    }

    public function guardianLabel(): string
    {
        return $this->guardian_name ?: ($this->guardian?->name ?? __('Guardian'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fees')
            ->logOnly(['school_id', 'reference', 'student_name', 'guardian_user_id', 'total_kobo', 'paid_kobo', 'due_date', 'status', 'claim_reference'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
