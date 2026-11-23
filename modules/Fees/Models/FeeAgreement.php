<?php

namespace Modules\Fees\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A parent's signed fee agreement for one child, with their consents to SMS
 * reminders and to the debtor check. Part of the claim pack if fees go unpaid.
 *
 * @property bool $consent_reminders
 * @property bool $consent_debtor_check
 */
class FeeAgreement extends Model
{
    use BelongsToSchool, LogsActivity;

    protected $fillable = [
        'school_id', 'guardian_user_id', 'student_name', 'signed_at', 'consent_reminders', 'consent_debtor_check', 'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
            'consent_reminders' => 'boolean',
            'consent_debtor_check' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fees')
            ->logOnly(['school_id', 'guardian_user_id', 'student_name', 'signed_at', 'consent_reminders', 'consent_debtor_check'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
