<?php

namespace Modules\Claims\Services;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\Scope\ActiveContext;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Claims\Enums\ClaimEventType;
use Modules\Claims\Enums\ClaimStage;
use Modules\Claims\Exceptions\ClaimsNotEnabled;
use Modules\Claims\Models\ClaimCase;
use Modules\Claims\Models\ClaimEvent;
use Modules\Claims\Models\ClaimFee;
use Modules\Claims\Models\ClaimHearing;
use Modules\Claims\Models\ClaimSettlement;
use Modules\Claims\Models\StateClaimSetting;
use Modules\Claims\Notifications\ClaimAssigned;
use Modules\Claims\Notifications\ClaimStageChanged;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Everything that changes a claim goes through here, so each change lands on
 * the case timeline (claim_events) and money and stage moves reach the audit log.
 */
class ClaimService
{
    /**
     * CLM-1: open a claim for a school. The school's state must have claims on.
     *
     * @param  array{debtor_name: string, debtor_phone?: ?string, amount_kobo: int, days_overdue?: ?int, summary?: ?string}  $data
     */
    public function openCase(User $by, School $school, array $data): ClaimCase
    {
        $this->ensureStateEnabled($school);

        $case = DB::transaction(function () use ($by, $school, $data) {
            $case = ClaimCase::query()->create([
                'reference' => $this->nextReference($school),
                'school_id' => $school->getKey(),
                'opened_by' => $by->getKey(),
                'debtor_name' => trim($data['debtor_name']),
                'debtor_phone' => PhoneNumber::normalize($data['debtor_phone'] ?? null) ?? ($data['debtor_phone'] ?? null),
                'amount_kobo' => $data['amount_kobo'],
                'days_overdue' => $data['days_overdue'] ?? 90,
                'stage' => ClaimStage::DemandSent,
                'summary' => $data['summary'] ?? null,
                'escalated_at' => now(),
            ]);

            $this->record($case, ClaimEventType::StageChange, $by, __('Claim opened by :school.', ['school' => $school->name]), [
                'from' => null,
                'to' => ClaimStage::DemandSent->value,
                'amount_kobo' => $case->amount_kobo,
            ]);

            activity('claims')->performedOn($case)->causedBy($by)->event('opened')
                ->withProperties(['reference' => $case->reference, 'amount_kobo' => $case->amount_kobo, 'stage' => $case->stage->value])
                ->log('Opened claim '.$case->reference);

            return $case;
        });

        return $case->load('school');
    }

    /**
     * Whether debt claims are switched on for a state.
     */
    public function isStateEnabled(?int $stateId): bool
    {
        if ($stateId === null) {
            return false;
        }

        return StateClaimSetting::query()->where('state_id', $stateId)->where('enabled', true)->exists();
    }

    /**
     * @throws ClaimsNotEnabled
     */
    public function ensureStateEnabled(School $school): void
    {
        if (! $this->isStateEnabled($school->state_id)) {
            throw ClaimsNotEnabled::forState($school->state?->name);
        }
    }

    /**
     * Move the case to another stage. Closing stamps closed_at; reopening clears it.
     */
    public function transitionStage(ClaimCase $case, ClaimStage $to, User $by, ?string $note = null): ClaimCase
    {
        $from = $case->stage;

        if ($from === $to) {
            return $case;
        }

        DB::transaction(function () use ($case, $to, $by, $note, $from) {
            $case->forceFill([
                'stage' => $to,
                'closed_at' => $to->isClosed() ? ($case->closed_at ?? now()) : null,
            ])->save();

            $this->record($case, ClaimEventType::StageChange, $by, $note, ['from' => $from->value, 'to' => $to->value]);

            activity('claims')->performedOn($case)->causedBy($by)->event('stage_changed')
                ->withProperties(['from' => $from->value, 'to' => $to->value, 'reference' => $case->reference])
                ->log("Claim {$case->reference} moved from {$from->value} to {$to->value}");
        });

        $recipients = collect([$case->opener, $case->lawyer])
            ->filter(fn (?User $u) => $u !== null && $u->isNot($by))
            ->unique('id');

        Notification::send($recipients, new ClaimStageChanged($case, $from, $to));

        return $case;
    }

    /**
     * Put a claim in the hands of a panel lawyer.
     */
    public function assignLawyer(ClaimCase $case, User $lawyer, User $by): ClaimCase
    {
        if (! $lawyer->holdsRole(Role::LegalPanel)) {
            throw new DomainException(__(':name is not on the legal panel.', ['name' => $lawyer->name]));
        }

        if ($case->lawyer_id === $lawyer->getKey()) {
            return $case;
        }

        $previous = $case->lawyer_id;

        DB::transaction(function () use ($case, $lawyer, $by, $previous) {
            $case->forceFill(['lawyer_id' => $lawyer->getKey()])->save();

            $this->record($case, ClaimEventType::Assignment, $by, __(':name was assigned to this claim.', ['name' => $lawyer->name]), [
                'lawyer_id' => $lawyer->getKey(),
                'previous_lawyer_id' => $previous,
            ]);

            activity('claims')->performedOn($case)->causedBy($by)->event('assigned')
                ->withProperties(['lawyer_id' => $lawyer->getKey(), 'previous_lawyer_id' => $previous])
                ->log("Claim {$case->reference} assigned to {$lawyer->name}");
        });

        if ($lawyer->isNot($by)) {
            $lawyer->notify(new ClaimAssigned($case->loadMissing('school')));
        }

        return $case;
    }

    public function addNote(ClaimCase $case, User $by, string $body): ClaimEvent
    {
        return $this->record($case, ClaimEventType::Note, $by, trim($body));
    }

    /**
     * Apply an edit from the case form: stage, court details and a note.
     * Returns what changed, in words.
     *
     * @param  array{stage?: ?string, stage_note?: ?string, court_name?: ?string, court_file_number?: ?string, notes?: ?string}  $data
     * @return list<string>
     */
    public function updateCase(ClaimCase $case, User $by, array $data): array
    {
        $changes = [];

        return DB::transaction(function () use ($case, $by, $data, &$changes) {
            $court = [
                'court_name' => array_key_exists('court_name', $data) ? $this->nullIfBlank($data['court_name']) : $case->court_name,
                'court_file_number' => array_key_exists('court_file_number', $data) ? $this->nullIfBlank($data['court_file_number']) : $case->court_file_number,
            ];

            if ($court['court_name'] !== $case->court_name || $court['court_file_number'] !== $case->court_file_number) {
                $case->forceFill($court)->save();
                $this->record($case, ClaimEventType::Filing, $by, collect([
                    $court['court_name'],
                    $court['court_file_number'] ? __('File no. :no', ['no' => $court['court_file_number']]) : null,
                ])->filter()->implode(' · ') ?: __('Court details cleared.'), $court);
                activity('claims')->performedOn($case)->causedBy($by)->event('court_updated')->withProperties($court)->log("Court details updated on {$case->reference}");
                $changes[] = __('Court details saved.');
            }

            $stage = isset($data['stage']) && $data['stage'] !== '' ? ClaimStage::from($data['stage']) : null;

            if ($stage !== null && $stage !== $case->stage) {
                $this->transitionStage($case, $stage, $by, $this->nullIfBlank($data['stage_note'] ?? null));
                $changes[] = __('Stage is now :stage.', ['stage' => $stage->label()]);
            }

            if (filled($data['notes'] ?? null)) {
                $this->addNote($case, $by, $data['notes']);
                $changes[] = __('Note added.');
            }

            return $changes;
        });
    }

    public function scheduleHearing(ClaimCase $case, User $by, CarbonInterface $at, ?string $venue = null): ClaimHearing
    {
        return DB::transaction(function () use ($case, $by, $at, $venue) {
            $hearing = $case->hearings()->create([
                'scheduled_at' => $at,
                'venue' => $this->nullIfBlank($venue) ?? $case->court_name,
                'created_by' => $by->getKey(),
            ]);

            $this->record($case, ClaimEventType::Hearing, $by, __('Hearing set for :when:venue.', [
                'when' => $at->format('j M Y, g:ia'),
                'venue' => $hearing->venue ? ' '.__('at :venue', ['venue' => $hearing->venue]) : '',
            ]), ['hearing_id' => $hearing->getKey(), 'scheduled_at' => $at->toIso8601String()]);

            return $hearing;
        });
    }

    /**
     * Reschedule a hearing or record what happened at it.
     *
     * @param  array{scheduled_at?: ?CarbonInterface, venue?: ?string, outcome?: ?string}  $data
     */
    public function updateHearing(ClaimHearing $hearing, User $by, array $data): ClaimHearing
    {
        return DB::transaction(function () use ($hearing, $by, $data) {
            $before = $hearing->scheduled_at;

            if (isset($data['scheduled_at'])) {
                $hearing->setAttribute('scheduled_at', $data['scheduled_at']);
            }
            if (array_key_exists('venue', $data)) {
                $hearing->venue = $this->nullIfBlank($data['venue']);
            }
            $outcomeAdded = array_key_exists('outcome', $data)
                && $this->nullIfBlank($data['outcome']) !== null
                && $data['outcome'] !== $hearing->outcome;
            if (array_key_exists('outcome', $data)) {
                $hearing->outcome = $this->nullIfBlank($data['outcome']);
            }

            if (! $hearing->isDirty()) {
                return $hearing;
            }

            $moved = $hearing->isDirty('scheduled_at');
            $hearing->save();

            $body = $outcomeAdded
                ? __('Hearing of :when: :outcome', ['when' => $hearing->scheduled_at->format('j M Y'), 'outcome' => $hearing->outcome])
                : ($moved
                    ? __('Hearing moved from :from to :to.', ['from' => $before->format('j M Y, g:ia'), 'to' => $hearing->scheduled_at->format('j M Y, g:ia')])
                    : __('Hearing details updated.'));

            $this->record($hearing->claimCase, ClaimEventType::Hearing, $by, $body, ['hearing_id' => $hearing->getKey()]);

            return $hearing;
        });
    }

    public function recordSettlement(ClaimCase $case, User $by, int $amountKobo, ?string $terms = null, ?CarbonInterface $paidAt = null): ClaimSettlement
    {
        return DB::transaction(function () use ($case, $by, $amountKobo, $terms, $paidAt) {
            $settlement = $case->settlements()->create([
                'amount_kobo' => $amountKobo,
                'terms' => $this->nullIfBlank($terms),
                'paid_at' => $paidAt,
                'recorded_by' => $by->getKey(),
            ]);

            $this->record($case, ClaimEventType::Settlement, $by, $paidAt
                ? __(':amount settled and paid on :date.', ['amount' => Money::format($amountKobo), 'date' => $paidAt->format('j M Y')])
                : __('Settlement of :amount agreed.', ['amount' => Money::format($amountKobo)]), [
                    'settlement_id' => $settlement->getKey(),
                    'amount_kobo' => $amountKobo,
                    'paid' => $paidAt !== null,
                ]);

            activity('claims')->performedOn($case)->causedBy($by)->event('settlement_recorded')
                ->withProperties(['amount_kobo' => $amountKobo, 'paid_at' => $paidAt?->toIso8601String(), 'reference' => $case->reference])
                ->log("Settlement of {$amountKobo} kobo recorded on {$case->reference}");

            return $settlement;
        });
    }

    public function recordFee(ClaimCase $case, User $by, string $description, int $amountKobo): ClaimFee
    {
        return DB::transaction(function () use ($case, $by, $description, $amountKobo) {
            $fee = $case->fees()->create([
                'description' => trim($description),
                'amount_kobo' => $amountKobo,
                'recorded_by' => $by->getKey(),
            ]);

            $this->record($case, ClaimEventType::Payment, $by, __(':description: :amount', [
                'description' => $fee->description,
                'amount' => Money::format($amountKobo),
            ]), ['fee_id' => $fee->getKey(), 'amount_kobo' => $amountKobo]);

            activity('claims')->performedOn($case)->causedBy($by)->event('fee_recorded')
                ->withProperties(['amount_kobo' => $amountKobo, 'description' => $fee->description, 'reference' => $case->reference])
                ->log("Fee of {$amountKobo} kobo recorded on {$case->reference}");

            return $fee;
        });
    }

    /**
     * Attach a file to one of the case's document collections.
     */
    public function addDocument(ClaimCase $case, User $by, UploadedFile $file, string $collection, ?string $title = null): Media
    {
        if (! array_key_exists($collection, ClaimCase::DOCUMENTS)) {
            throw new DomainException(__('Unknown document type.'));
        }

        return DB::transaction(function () use ($case, $by, $file, $collection, $title) {
            $adder = $case->addMedia($file)->withCustomProperties(['uploaded_by' => $by->getKey()]);

            if (filled($title)) {
                $adder->usingName(trim((string) $title));
            }

            $media = $adder->toMediaCollection($collection);

            $this->record($case, ClaimEventType::Document, $by, __(':kind added: :name', [
                'kind' => __(ClaimCase::DOCUMENTS[$collection]),
                'name' => $media->name,
            ]), ['collection' => $collection, 'media_id' => $media->getKey()]);

            return $media;
        });
    }

    /**
     * Fill the library's demand letter template for this claim and file it
     * under "Demand letter". Needs the Templates module and a published
     * "demand_letter" template.
     */
    public function generateDemandLetter(ClaimCase $case, User $by): Media
    {
        $service = 'Modules\\Templates\\Services\\TemplateService';

        if (! class_exists($service)) {
            throw new DomainException(__('Document templates are not available yet.'));
        }

        $case->loadMissing('school');
        $school = $case->school;

        try {
            $pdf = app($service)->render('demand_letter', [
                'school_name' => $school->name,
                'school_address' => $school->address,
                'school_phone' => PhoneNumber::format($school->phone),
                'date' => now()->toDateString(),
                'debtor_name' => $case->debtor_name,
                'amount' => Money::toNaira($case->amount_kobo),
                'due_date' => now()->addDays(14)->toDateString(),
                'signatory_name' => $by->name,
                'signatory_title' => __('Proprietor'),
            ]);
        } catch (ModelNotFoundException) {
            throw new DomainException(__('The demand letter template has not been published yet.'));
        }

        return DB::transaction(function () use ($case, $by, $pdf) {
            $media = $case->addMediaFromString($pdf)
                ->usingName(__('Demand letter').' '.$case->reference)
                ->usingFileName('demand-letter-'.strtolower($case->reference).'.pdf')
                ->withCustomProperties(['uploaded_by' => $by->getKey(), 'generated' => true])
                ->toMediaCollection('demand_letter');

            $this->record($case, ClaimEventType::Document, $by, __('Demand letter generated from the template.'), [
                'collection' => 'demand_letter',
                'media_id' => $media->getKey(),
            ]);

            return $media;
        });
    }

    /**
     * What the claim pack holds and what is still missing, for the case page.
     * Pleadings and the judgment become required as the case reaches them.
     *
     * @return list<array{key: string, title: string, status: 'ready'|'missing'|'optional', meta: ?string, files: list<array{id: int, name: string, url: string, at: string}>}>
     */
    public function buildPackChecklist(ClaimCase $case): array
    {
        $case->loadMissing('media');
        $order = array_search($case->stage, ClaimStage::pipeline(), true);

        $required = [
            'demand_letter' => true,
            'evidence' => true,
            'pleadings' => $order >= array_search(ClaimStage::Filed, ClaimStage::pipeline(), true),
            'judgment' => $order >= array_search(ClaimStage::Judgment, ClaimStage::pipeline(), true),
        ];

        $rows = [];

        foreach (ClaimCase::DOCUMENTS as $key => $label) {
            $files = $case->getMedia($key);

            $rows[] = [
                'key' => $key,
                'title' => __($label),
                'status' => $files->isNotEmpty() ? 'ready' : ($required[$key] ? 'missing' : 'optional'),
                'meta' => $files->isNotEmpty()
                    ? trans_choice(':count file|:count files', $files->count()).' · '.__('latest :date', ['date' => $files->sortByDesc('created_at')->first()?->created_at?->format('j M Y')])
                    : null,
                'files' => $files->map(fn (Media $m) => [
                    'id' => $m->getKey(),
                    'name' => $m->name,
                    'url' => route('claims.documents.show', [$case, $m]),
                    'at' => $m->created_at?->format('j M Y') ?? '',
                ])->values()->all(),
            ];
        }

        return $rows;
    }

    /**
     * Totals for the case panel.
     *
     * @return array{settled_kobo: int, paid_kobo: int, fees_kobo: int, outstanding_kobo: int}
     */
    public function totals(ClaimCase $case): array
    {
        $settled = (int) $case->settlements()->sum('amount_kobo');
        $paid = (int) $case->settlements()->whereNotNull('paid_at')->sum('amount_kobo');

        return [
            'settled_kobo' => $settled,
            'paid_kobo' => $paid,
            'fees_kobo' => (int) $case->fees()->sum('amount_kobo'),
            'outstanding_kobo' => max(0, $case->amount_kobo - $paid),
        ];
    }

    /**
     * A panel lawyer's home dashboard: their open files and this week's hearings.
     *
     * @return array{open: Collection<int, ClaimCase>, open_count: int, hearings: Collection<int, ClaimHearing>, exposure_kobo: int}
     */
    public function legalSummary(User $lawyer): array
    {
        $base = ClaimCase::query()->where('lawyer_id', $lawyer->getKey())->open();

        return [
            'open' => (clone $base)->with(['school', 'hearings'])->latest('escalated_at')->limit(8)->get(),
            'open_count' => (clone $base)->count(),
            'hearings' => $this->hearingsThisWeek(fn ($q) => $q->where('lawyer_id', $lawyer->getKey())),
            'exposure_kobo' => (int) (clone $base)->sum('amount_kobo'),
        ];
    }

    /**
     * Scoped counts for executive and secretariat dashboards.
     *
     * @return array{open: int, hearings: int, recovered_kobo: int, in_court: int}
     */
    public function overview(ActiveContext $context): array
    {
        $visible = fn () => ClaimCase::query()->visibleTo($context);

        return [
            'open' => $visible()->open()->count(),
            'in_court' => $visible()->whereIn('stage', [ClaimStage::Filed->value, ClaimStage::Hearing->value, ClaimStage::Judgment->value])->count(),
            'hearings' => $this->hearingsThisWeek(fn ($q) => $q->visibleTo($context))->count(),
            'recovered_kobo' => (int) ClaimSettlement::query()
                ->whereNotNull('paid_at')
                ->whereIn('claim_case_id', $visible()->select('claim_cases.id'))
                ->sum('amount_kobo'),
        ];
    }

    /**
     * Hearings from Monday to Sunday of this week, soonest first.
     *
     * @param  callable(Builder<ClaimCase>): mixed  $constrain
     * @return Collection<int, ClaimHearing>
     */
    public function hearingsThisWeek(callable $constrain): Collection
    {
        return ClaimHearing::query()
            ->with('claimCase.school')
            ->whereBetween('scheduled_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->whereHas('claimCase', function ($q) use ($constrain) {
                $q->open();
                $constrain($q);
            })
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Append a row to the case timeline.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function record(ClaimCase $case, ClaimEventType $type, ?User $by, ?string $body = null, ?array $meta = null): ClaimEvent
    {
        return $case->events()->create([
            'user_id' => $by?->getKey(),
            'type' => $type,
            'body' => $this->nullIfBlank($body),
            'meta' => $meta,
        ]);
    }

    private function nextReference(School $school): string
    {
        $prefix = sprintf('CLM-%s-%d-', $school->state->code, now()->year);

        $last = ClaimCase::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $next = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
