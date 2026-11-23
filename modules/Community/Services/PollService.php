<?php

namespace Modules\Community\Services;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Community\Models\Poll;
use Modules\Community\Models\PollVote;
use Modules\Community\Models\Post;
use Modules\Community\Models\Space;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Quick polls (any member; optional anonymity; you can change your vote until
 * it closes) and formal votes (COM-4): one final ballot per member school in
 * good standing inside the space, results hidden until close, then locked and
 * exportable for the minutes.
 */
class PollService
{
    /**
     * @param  array<string, mixed>  $data  options (list of labels), multiple, anonymous, formal, closes_at
     */
    public function create(Post $post, array $data): Poll
    {
        $formal = (bool) ($data['formal'] ?? false);

        $poll = $post->poll()->create([
            'multiple' => ! $formal && (bool) ($data['multiple'] ?? false),
            'anonymous' => (bool) ($data['anonymous'] ?? false),
            'formal' => $formal,
            'closes_at' => $data['closes_at'] ?? null,
        ]);

        foreach (array_values($data['options']) as $i => $label) {
            $poll->options()->create(['label' => $label, 'position' => $i]);
        }

        if ($formal) {
            activity('community')->performedOn($poll)->event('vote-opened')->log('Formal vote opened');
        }

        return $poll;
    }

    /**
     * Member schools the user may cast a formal ballot for: schools they own
     * as proprietor, active (dues paid) and inside the poll's space.
     *
     * @return EloquentCollection<int, School>
     */
    public function eligibleSchools(User $user, Poll $poll): EloquentCollection
    {
        $schoolIds = RoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('scope_type', ScopeLevel::School)
            ->whereHas('role', fn (Builder $q) => $q->where('name', Role::Proprietor->value))
            ->pluck('scope_id');

        return $this->schoolsIn($poll->post->space)->whereIn('id', $schoolIds)->orderBy('name')->get();
    }

    /**
     * Schools with a vote in this space (the turnout denominator).
     */
    public function electorateSize(Poll $poll): int
    {
        return $this->schoolsIn($poll->post->space)->count();
    }

    /**
     * @param  array<int, int|string>  $optionIds
     */
    public function vote(User $user, Poll $poll, array $optionIds, ?int $schoolId = null): void
    {
        if ($poll->isClosed()) {
            throw ValidationException::withMessages(['poll' => __('Voting has closed.')]);
        }

        $optionIds = array_values(array_unique(array_map('intval', $optionIds)));
        $valid = $poll->options()->whereIn('id', $optionIds)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($valid === [] || count($valid) !== count($optionIds)) {
            throw ValidationException::withMessages(['poll' => __('Choose an option.')]);
        }

        if (! $poll->multiple && count($valid) > 1) {
            throw ValidationException::withMessages(['poll' => __('Choose one option.')]);
        }

        if (! $poll->formal) {
            DB::transaction(function () use ($user, $poll, $valid) {
                $poll->votes()->where('user_id', $user->id)->delete();
                foreach ($valid as $optionId) {
                    $poll->votes()->create(['option_id' => $optionId, 'user_id' => $user->id]);
                }
            });

            return;
        }

        $eligible = $this->eligibleSchools($user, $poll);
        $school = $schoolId !== null
            ? $eligible->firstWhere('id', $schoolId)
            : ($eligible->count() === 1 ? $eligible->first() : null);

        if (! $school) {
            throw ValidationException::withMessages(['poll' => __('Choose the member school you are voting for.')]);
        }

        $alreadyVoted = fn () => ValidationException::withMessages(['poll' => __(':school has already voted.', ['school' => $school->name])]);

        try {
            $cast = DB::transaction(function () use ($user, $poll, $valid, $school) {
                if ($poll->votes()->where('school_id', $school->id)->lockForUpdate()->exists()) {
                    return false;
                }

                $poll->votes()->create(['option_id' => $valid[0], 'user_id' => $user->id, 'school_id' => $school->id]);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            $cast = false; // a colleague at the same school voted a moment earlier
        }

        if (! $cast) {
            throw $alreadyVoted();
        }

        activity('community')->performedOn($poll)->causedBy($user)->event('ballot-cast')
            ->withProperties(['school_id' => $school->id])->log('Formal vote ballot cast');
    }

    public function close(Poll $poll, User $by): void
    {
        if ($poll->closed_at !== null) {
            return;
        }

        $poll->forceFill(['closed_at' => now(), 'closed_by' => $by->id])->save();

        activity('community')->performedOn($poll)->causedBy($by)->event('vote-closed')
            ->withProperties(['results' => $this->tally($poll)])
            ->log($poll->formal ? 'Formal vote closed' : 'Poll closed');
    }

    /**
     * @return array<int, int> option id => votes
     */
    public function tally(Poll $poll): array
    {
        return PollVote::query()->where('poll_id', $poll->id)
            ->selectRaw('option_id, count(*) as total')->groupBy('option_id')
            ->pluck('total', 'option_id')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * Ballots (formal: schools) or people (quick polls) who voted.
     */
    public function participants(Poll $poll): int
    {
        return (int) PollVote::query()->where('poll_id', $poll->id)
            ->distinct()->count($poll->formal ? 'school_id' : 'user_id');
    }

    /**
     * Everything the poll card needs for this viewer.
     *
     * @return array<string, mixed>
     */
    public function present(Poll $poll, User $user, SpaceDirectory $directory): array
    {
        $space = $poll->post->space;
        $closed = $poll->isClosed();
        $official = $directory->canRunFormalVote($user, $space) || $directory->canModerate($user, $space);
        $owner = $poll->post->author_id === $user->id;

        $mine = $poll->votes()->where('user_id', $user->id)->pluck('option_id')->map(fn ($id) => (int) $id)->all();
        $schools = collect();
        $notice = null;

        if ($poll->formal) {
            $eligible = $this->eligibleSchools($user, $poll);
            $voted = $poll->votes()->whereIn('school_id', $eligible->modelKeys())->pluck('school_id')->map(fn ($id) => (int) $id)->all();
            $schools = $eligible->reject(fn (School $s) => in_array($s->id, $voted, true))->values();
            $canVote = ! $closed && $schools->isNotEmpty();

            if ($eligible->isEmpty()) {
                $notice = __('Only proprietors of member schools with dues paid in :space can vote.', ['space' => $space->name]);
            } elseif ($schools->isEmpty()) {
                $notice = $eligible->count() === 1 ? __('Your school’s ballot is in.') : __('Ballots are in for all your schools.');
            }

            $showResults = $closed;
        } else {
            $canVote = ! $closed && $directory->canView($user, $space);
            $showResults = $closed || $mine !== [];
        }

        return [
            'closed' => $closed,
            'canVote' => $canVote,
            'showResults' => $showResults,
            'mine' => $mine,
            'schools' => $schools,
            'notice' => $notice,
            'tally' => $showResults ? $this->tally($poll) : [],
            'participants' => $this->participants($poll),
            'electorate' => $poll->formal ? $this->electorateSize($poll) : null,
            'canClose' => ! $closed && ($owner || $official),
            'canExport' => $closed && ($poll->formal ? $official : ($owner || $official)),
        ];
    }

    /**
     * Results for the minutes: totals, turnout and (unless anonymous) who chose what.
     */
    public function export(Poll $poll): StreamedResponse
    {
        $poll->loadMissing(['post.space', 'options']);
        $tally = $this->tally($poll);
        $votes = $poll->votes()->with(['option', 'user', 'school'])->orderBy('created_at')->orderBy('id')->get();

        return response()->streamDownload(function () use ($poll, $tally, $votes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$poll->formal ? __('Formal vote') : __('Poll'), $poll->post->body]);
            fputcsv($out, [__('Space'), $poll->post->space->name]);
            fputcsv($out, [__('Closed'), $poll->endedAt()?->timezone(config('app.timezone'))->format('j M Y, g:ia')]);
            if ($poll->formal) {
                fputcsv($out, [__('Turnout'), __(':voted of :total member schools', ['voted' => $this->participants($poll), 'total' => $this->electorateSize($poll)])]);
            }
            fputcsv($out, []);
            fputcsv($out, [__('Option'), __('Votes')]);
            foreach ($poll->options as $option) {
                fputcsv($out, [$option->label, $tally[$option->id] ?? 0]);
            }
            fputcsv($out, []);

            $header = $poll->formal
                ? [__('School'), __('Membership number'), __('Cast by'), __('Cast at')]
                : [__('Member'), __('Voted at')];
            if (! $poll->anonymous) {
                $header[] = __('Choice');
            }
            fputcsv($out, $header);

            foreach ($votes as $vote) {
                $row = $poll->formal
                    ? [$vote->school?->name, $vote->school?->membership_number, $vote->user?->name, $vote->created_at?->format('Y-m-d H:i')]
                    : [$poll->anonymous ? __('Anonymous') : $vote->user?->name, $vote->created_at?->format('Y-m-d H:i')];
                if (! $poll->anonymous) {
                    $row[] = $vote->option?->label;
                }
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'vote-'.$poll->id.'-results.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Builder<School>
     */
    private function schoolsIn(Space $space): Builder
    {
        return School::withoutGlobalScopes()
            ->where('membership_status', MembershipStatus::Active)
            ->when($space->type === 'chapter', fn (Builder $q) => $q->where('chapter_id', $space->chapter_id))
            ->when($space->type === 'state', fn (Builder $q) => $q->whereHas('chapter', fn (Builder $c) => $c->where('state_id', $space->state_id)))
            ->when(! in_array($space->type, ['national', 'state', 'chapter'], true), fn (Builder $q) => $q->whereRaw('1 = 0'));
    }
}
