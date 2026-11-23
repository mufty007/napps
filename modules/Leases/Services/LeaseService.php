<?php

namespace Modules\Leases\Services;

use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\School;
use App\Models\User;
use App\Support\Money;
use App\Support\PhoneNumber;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Leases\Enums\DisputeStatus;
use Modules\Leases\Enums\LeaseStatus;
use Modules\Leases\Enums\RentFrequency;
use Modules\Leases\Models\Lease;
use Modules\Leases\Models\LeaseDispute;
use Modules\Leases\Models\LeaseRentPayment;
use Modules\Leases\Notifications\LeaseDisputeUpdated;
use Modules\Leases\Notifications\LeaseRenewalReminder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Everything that changes a lease, its rent log or its disputes goes through
 * here, so money and dispute moves always reach the audit log.
 */
class LeaseService
{
    /**
     * Register a lease for a school.
     *
     * @param  array{landlord_name: string, landlord_phone?: ?string, property_address: string, start_date: string, end_date: string, rent_kobo: int, rent_frequency: string, notes?: ?string}  $data
     */
    public function createLease(School $school, array $data): Lease
    {
        $lease = new Lease($this->attributes($data));
        $lease->school_id = $school->getKey();
        $lease->status = $this->statusFor($lease->end_date);
        $lease->save();

        return $lease;
    }

    /**
     * Edit a lease. `status` may be changed by hand to active, expired or
     * terminated; a lease in dispute keeps that status until the dispute ends.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateLease(Lease $lease, array $data): Lease
    {
        $lease->fill($this->attributes($data));

        if (isset($data['status']) && $lease->status !== LeaseStatus::Disputed) {
            $status = LeaseStatus::tryFrom((string) $data['status']);

            if ($status !== null && in_array($status, LeaseStatus::manual(), true)) {
                $lease->status = $status;
            }
        }

        $lease->save();

        return $lease;
    }

    /**
     * Log a rent payment (money: audit-logged).
     */
    public function recordRent(Lease $lease, User $by, int $amountKobo, CarbonInterface $paidAt, ?string $method = null, ?string $notes = null): LeaseRentPayment
    {
        if ($amountKobo <= 0) {
            throw new DomainException(__('Enter the amount in naira, for example 25000.'));
        }

        return DB::transaction(function () use ($lease, $by, $amountKobo, $paidAt, $method, $notes) {
            $payment = $lease->rentPayments()->create([
                'amount_kobo' => $amountKobo,
                'paid_at' => $paidAt->toDateString(),
                'method' => $this->nullIfBlank($method),
                'recorded_by' => $by->getKey(),
                'notes' => $this->nullIfBlank($notes),
            ]);

            activity('leases')->performedOn($lease)->causedBy($by)->event('rent_recorded')
                ->withProperties([
                    'amount_kobo' => $amountKobo,
                    'paid_at' => $paidAt->toDateString(),
                    'method' => $payment->method,
                    'payment_id' => $payment->getKey(),
                ])
                ->log("Rent of {$amountKobo} kobo recorded on lease {$lease->getKey()}");

            return $payment;
        });
    }

    /**
     * Total rent logged on a lease, in kobo.
     */
    public function totalPaid(Lease $lease): int
    {
        return (int) $lease->rentPayments()->sum('amount_kobo');
    }

    /**
     * File a document in the lease vault.
     */
    public function addDocument(Lease $lease, User $by, UploadedFile $file, ?string $title = null): Media
    {
        $adder = $lease->addMedia($file)->withCustomProperties(['uploaded_by' => $by->getKey()]);

        if (filled($title)) {
            $adder->usingName(trim((string) $title));
        }

        $media = $adder->toMediaCollection(Lease::VAULT);

        activity('leases')->performedOn($lease)->causedBy($by)->event('document_added')
            ->withProperties(['media_id' => $media->getKey(), 'name' => $media->name])
            ->log("Document added to lease {$lease->getKey()}");

        return $media;
    }

    /**
     * Whether the Templates module is installed, for the "model lease" button.
     */
    public function canGenerateModelLease(): bool
    {
        return class_exists('Modules\\Templates\\Services\\TemplateService');
    }

    /**
     * Fill the library's `model_lease` template from this lease and file the
     * PDF in the vault. Needs the Templates module and a published template.
     */
    public function generateModelLease(Lease $lease, User $by): Media
    {
        $service = 'Modules\\Templates\\Services\\TemplateService';

        if (! $this->canGenerateModelLease()) {
            throw new DomainException(__('Document templates are not available yet.'));
        }

        $lease->loadMissing('school');
        $school = $lease->school;
        $months = (int) round($lease->start_date->diffInMonths($lease->end_date->copy()->addDay()));

        try {
            $pdf = app($service)->render('model_lease', [
                'date' => now()->toDateString(),
                'landlord_name' => $lease->landlord_name,
                'tenant_name' => $by->name,
                'school_name' => $school?->name,
                'premises_address' => $lease->property_address,
                'lease_term' => $months >= 12 && $months % 12 === 0
                    ? trans_choice(':count year|:count years', intdiv($months, 12))
                    : trans_choice(':count month|:count months', max(1, $months)),
                'commencement_date' => $lease->start_date->toDateString(),
                'annual_rent' => Money::toNaira($lease->annualRentKobo()),
            ]);
        } catch (ModelNotFoundException) {
            throw new DomainException(__('The model lease template has not been published yet.'));
        }

        $media = $lease->addMediaFromString($pdf)
            ->usingName(__('Model lease (draft)'))
            ->usingFileName('model-lease-'.$lease->getKey().'.pdf')
            ->withCustomProperties(['uploaded_by' => $by->getKey(), 'generated' => true])
            ->toMediaCollection(Lease::VAULT);

        activity('leases')->performedOn($lease)->causedBy($by)->event('model_lease_generated')
            ->withProperties(['media_id' => $media->getKey()])
            ->log("Model lease generated for lease {$lease->getKey()}");

        return $media;
    }

    /**
     * A school opens a dispute with its landlord. The lease turns "in dispute"
     * and the school's chapter executives are told.
     */
    public function openDispute(Lease $lease, User $by, string $summary): LeaseDispute
    {
        if ($lease->hasOpenDispute()) {
            throw new DomainException(__('This lease already has an open dispute.'));
        }

        $dispute = DB::transaction(function () use ($lease, $by, $summary) {
            $dispute = $lease->disputes()->create([
                'school_id' => $lease->school_id,
                'opened_by' => $by->getKey(),
                'summary' => trim($summary),
                'status' => DisputeStatus::Open,
            ]);

            if (in_array($lease->status, [LeaseStatus::Active, LeaseStatus::Expired], true)) {
                $lease->forceFill(['status' => LeaseStatus::Disputed])->save();
            }

            activity('leases')->performedOn($dispute)->causedBy($by)->event('dispute_opened')
                ->withProperties(['lease_id' => $lease->getKey(), 'school_id' => $lease->school_id])
                ->log("Dispute opened on lease {$lease->getKey()}");

            return $dispute;
        });

        $this->notify($this->chapterExecutives($dispute), $dispute, 'opened', $by);

        return $dispute;
    }

    /**
     * Ask the chapter (or a chapter executive offering) to step in.
     */
    public function requestChapterHelp(LeaseDispute $dispute, User $by): LeaseDispute
    {
        if ($dispute->status !== DisputeStatus::Open) {
            return $dispute;
        }

        $this->moveTo($dispute, DisputeStatus::ChapterHelp, $by, 'chapter_help_requested');
        $this->notify($this->chapterExecutives($dispute), $dispute, 'chapter_help', $by);

        return $dispute;
    }

    /**
     * Hand the dispute to the legal panel, optionally naming the lawyer.
     * Calling it again on a dispute already with the panel changes the lawyer.
     */
    public function escalateToLegal(LeaseDispute $dispute, User $by, ?User $lawyer = null): LeaseDispute
    {
        if (! $dispute->isOpen()) {
            throw new DomainException(__('This dispute is already resolved.'));
        }

        if ($lawyer !== null && ! $lawyer->holdsRole(Role::LegalPanel)) {
            throw new DomainException(__(':name is not on the legal panel.', ['name' => $lawyer->name]));
        }

        $wasLegal = $dispute->status === DisputeStatus::Legal;
        $previous = $dispute->assigned_lawyer_id;

        DB::transaction(function () use ($dispute, $by, $lawyer, $previous) {
            $dispute->forceFill([
                'status' => DisputeStatus::Legal,
                'assigned_lawyer_id' => $lawyer?->getKey() ?? $dispute->assigned_lawyer_id,
            ])->save();

            activity('leases')->performedOn($dispute)->causedBy($by)->event('escalated_to_legal')
                ->withProperties(['lawyer_id' => $dispute->assigned_lawyer_id, 'previous_lawyer_id' => $previous])
                ->log("Lease dispute {$dispute->getKey()} passed to the legal panel");
        });

        $dispute->unsetRelation('lawyer');

        $newLawyer = $lawyer !== null && $lawyer->getKey() !== $previous;

        if (! $wasLegal || $newLawyer) {
            $this->notify(collect([$dispute->opener, $dispute->lawyer]), $dispute, 'legal', $by);
        }

        return $dispute;
    }

    /**
     * Close a dispute. The lease goes back to active (or expired if its term
     * has ended) unless another dispute is still open.
     */
    public function resolveDispute(LeaseDispute $dispute, User $by, ?string $notes = null): LeaseDispute
    {
        if (! $dispute->isOpen()) {
            return $dispute;
        }

        DB::transaction(function () use ($dispute, $by, $notes) {
            $dispute->forceFill([
                'status' => DisputeStatus::Resolved,
                'resolved_at' => now(),
                'resolution_notes' => $this->nullIfBlank($notes),
            ])->save();

            $lease = $dispute->lease;

            if ($lease->status === LeaseStatus::Disputed && ! $lease->hasOpenDispute()) {
                $lease->forceFill(['status' => $this->statusFor($lease->end_date)])->save();
            }

            activity('leases')->performedOn($dispute)->causedBy($by)->event('dispute_resolved')
                ->withProperties(['lease_id' => $dispute->lease_id])
                ->log("Lease dispute {$dispute->getKey()} resolved");
        });

        $this->notify(collect([$dispute->opener, $dispute->lawyer]), $dispute, 'resolved', $by);

        return $dispute;
    }

    /**
     * Mark active leases that have run past their end date as expired.
     * Runs without a user, so it ignores the school scope on purpose.
     */
    public function markExpired(): int
    {
        return Lease::withoutGlobalScopes()
            ->where('status', LeaseStatus::Active->value)
            ->whereDate('end_date', '<', today())
            ->update(['status' => LeaseStatus::Expired->value]);
    }

    /**
     * Send the 90/60/30 day renewal reminders to each school's proprietors.
     *
     * A lease ending within 90 days sits in a window: 90 (61-90 days left),
     * 60 (31-60) or 30 (0-30). Each window is sent once per lease and person,
     * so a missed day catches up and re-running the command is harmless.
     * Returns the number of reminders sent.
     */
    public function sendRenewalReminders(?CarbonInterface $today = null): int
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $sent = 0;

        $leases = Lease::withoutGlobalScopes()
            ->live()
            ->whereDate('end_date', '>=', $today->toDateString())
            ->whereDate('end_date', '<=', $today->copy()->addDays(max(Lease::REMINDER_DAYS))->toDateString())
            ->with('school')
            ->get();

        foreach ($leases as $lease) {
            $days = (int) $today->diffInDays($lease->end_date->copy()->startOfDay(), false);
            $window = $this->windowFor($days);

            if ($window === null) {
                continue;
            }

            $proprietors = User::query()->holdingRole(Role::Proprietor, ScopeLevel::School, $lease->school_id)->get();

            foreach ($proprietors as $user) {
                $already = $user->notifications()
                    ->where('type', LeaseRenewalReminder::class)
                    ->where('data->lease_id', $lease->getKey())
                    ->where('data->window', $window)
                    ->exists();

                if ($already) {
                    continue;
                }

                $user->notify(new LeaseRenewalReminder($lease, $days, $window));
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * The reminder window (90, 60 or 30) a lease with this many days left is in.
     */
    public function windowFor(int $daysLeft): ?int
    {
        if ($daysLeft < 0) {
            return null;
        }

        $windows = collect(Lease::REMINDER_DAYS)->sort()->values();

        return $windows->first(fn (int $window) => $daysLeft <= $window);
    }

    /**
     * Scoped counts for the lease register header.
     *
     * @return array{total: int, expiring: int, in_dispute: int, expired: int}
     */
    public function register(): array
    {
        $soon = today()->addDays(max(Lease::REMINDER_DAYS))->toDateString();

        return [
            'total' => Lease::query()->count(),
            'expiring' => Lease::query()->live()->whereDate('end_date', '>=', today()->toDateString())->whereDate('end_date', '<=', $soon)->count(),
            'in_dispute' => Lease::query()->where('status', LeaseStatus::Disputed->value)->count(),
            'expired' => Lease::query()->where('status', LeaseStatus::Expired->value)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = array_intersect_key($data, array_flip([
            'landlord_name', 'landlord_phone', 'property_address', 'start_date', 'end_date', 'rent_kobo', 'rent_frequency', 'notes',
        ]));

        if (array_key_exists('landlord_name', $attributes)) {
            $attributes['landlord_name'] = trim((string) $attributes['landlord_name']);
        }

        if (array_key_exists('property_address', $attributes)) {
            $attributes['property_address'] = trim((string) $attributes['property_address']);
        }

        if (array_key_exists('landlord_phone', $attributes)) {
            $phone = $this->nullIfBlank($attributes['landlord_phone']);
            $attributes['landlord_phone'] = $phone === null ? null : (PhoneNumber::normalize($phone) ?? $phone);
        }

        if (array_key_exists('notes', $attributes)) {
            $attributes['notes'] = $this->nullIfBlank($attributes['notes']);
        }

        if (isset($attributes['rent_frequency']) && $attributes['rent_frequency'] instanceof RentFrequency) {
            $attributes['rent_frequency'] = $attributes['rent_frequency']->value;
        }

        return $attributes;
    }

    private function statusFor(CarbonInterface $endDate): LeaseStatus
    {
        return $endDate->copy()->startOfDay()->lt(today()) ? LeaseStatus::Expired : LeaseStatus::Active;
    }

    /**
     * @return Collection<int, User>
     */
    private function chapterExecutives(LeaseDispute $dispute): Collection
    {
        $chapterId = School::withoutGlobalScopes()->whereKey($dispute->school_id)->value('chapter_id');

        if ($chapterId === null) {
            return new Collection;
        }

        return User::query()->holdingRole(Role::ChapterExecutive, ScopeLevel::Chapter, (int) $chapterId)->get();
    }

    private function moveTo(LeaseDispute $dispute, DisputeStatus $to, User $by, string $event): void
    {
        $from = $dispute->status;

        DB::transaction(function () use ($dispute, $to, $by, $event, $from) {
            $dispute->forceFill(['status' => $to])->save();

            activity('leases')->performedOn($dispute)->causedBy($by)->event($event)
                ->withProperties(['from' => $from->value, 'to' => $to->value])
                ->log("Lease dispute {$dispute->getKey()} moved from {$from->value} to {$to->value}");
        });
    }

    /**
     * @param  iterable<int, ?User>  $recipients
     * @param  'opened'|'chapter_help'|'legal'|'resolved'  $event
     */
    private function notify(iterable $recipients, LeaseDispute $dispute, string $event, User $by): void
    {
        $people = collect($recipients)
            ->filter(fn (?User $user) => $user !== null && $user->isNot($by))
            ->unique('id')
            ->values();

        if ($people->isEmpty()) {
            return;
        }

        $dispute->loadMissing(['school', 'lease']);

        Notification::send($people, new LeaseDisputeUpdated($dispute, $event));
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
