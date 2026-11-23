<?php

namespace Modules\Community\Seeders;

use App\Models\Chapter;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Community\Models\Conversation;
use Modules\Community\Models\Forum;
use Modules\Community\Models\Message;
use Modules\Community\Models\Post;
use Modules\Community\Models\Space;
use Modules\Community\Models\Thread;
use Modules\Community\Services\PollService;
use Modules\Community\Services\SpaceDirectory;

class CommunityDemoSeeder extends Seeder
{
    public function run(SpaceDirectory $directory): void
    {
        $kano = Chapter::query()->where('name', 'Kano Chapter')->first();
        $katsina = Chapter::query()->where('name', 'Katsina North Chapter')->first();
        if (! $kano) {
            return;
        }

        $national = $directory->ensureNationalSpace();
        $kanoSpace = $directory->ensureChapterSpace($kano);
        $katsinaSpace = $katsina ? $directory->ensureChapterSpace($katsina) : null;

        $users = User::query()->whereIn('phone', [
            '+2348000000001', '+2348000000002', '+2348000000003', '+2348000000004',
            '+2348000000005', '+2348000000006', '+2348000000008', '+2348000000009',
            '+2348000000010', '+2348000000011', '+2348000000014',
        ])->get()->keyBy('phone');

        foreach ([$national, $kanoSpace, $katsinaSpace] as $space) {
            if (! $space) {
                continue;
            }
            foreach ($users as $user) {
                $directory->syncMembership($user, $space);
            }
        }

        $interest = Space::query()->firstOrCreate(
            ['slug' => 'nursery-proprietors-ng'],
            [
                'type' => 'interest',
                'name' => 'Nursery proprietors',
                'privacy' => 'open',
                'description' => 'Share ideas on early years fees, staffing and facilities.',
                'created_by' => $users['+2348000000004']->id ?? null,
            ],
        );
        foreach (['+2348000000003', '+2348000000004', '+2348000000011', '+2348000000014'] as $phone) {
            if ($users->has($phone)) {
                $directory->syncMembership($users[$phone], $interest);
            }
        }

        $forumDefs = [
            [$kanoSpace, 'Fee collection', 'fees', 'Tips and questions on getting parents to pay on time.'],
            [$kanoSpace, 'Landlords & facilities', 'facilities', 'Leases, renewals and premises disputes.'],
            [$kanoSpace, 'Legal & claims', 'legal', 'Demand letters, mediation and small claims.'],
            [$national, 'Government & regulation', 'policy', 'Ministry circulars and policy changes.'],
            [$national, 'Technology', 'tech', 'Software, POS and low-bandwidth tips.'],
        ];

        if ($katsinaSpace) {
            $forumDefs[] = [$katsinaSpace, 'Fee collection', 'fees', 'Chapter discussion on arrears and reminders.'];
        }

        foreach ($forumDefs as [$space, $name, $category, $description]) {
            Forum::query()->firstOrCreate(
                ['space_id' => $space->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'category' => $category, 'description' => $description],
            );
        }

        $hadiza = $users['+2348000000003'] ?? null;
        $bashir = $users['+2348000000004'] ?? null;
        $zainab = $users['+2348000000001'] ?? null;

        if ($bashir && $hadiza) {
            Post::query()->firstOrCreate(
                ['space_id' => $kanoSpace->id, 'author_id' => $hadiza->id, 'body' => 'Welcome to the Kano Chapter feed. Use this space for official chapter updates and peer advice — keep pupil fee details out of posts.'],
                ['type' => 'text', 'pinned' => true, 'created_at' => now()->subDays(3)],
            );

            Post::query()->firstOrCreate(
                ['space_id' => $kanoSpace->id, 'author_id' => $bashir->id, 'body' => 'We cut arrears from about 10% collection to nearly 40% last term after switching to instalment plans and SMS reminders. Happy to share the schedule we used.'],
                ['type' => 'showcase', 'created_at' => now()->subDay()],
            );

            $this->seedThreads($kanoSpace->id, $national->id, $users);
            $this->seedPolls($kanoSpace, $hadiza, $bashir);
        }

        if ($zainab) {
            Post::query()->firstOrCreate(
                ['space_id' => $national->id, 'author_id' => $zainab->id, 'body' => 'National update: the legal panel is live for Kano and Katsina schools. Escalate 90-day debts from Claims once your chapter mediation step is done.'],
                ['type' => 'text', 'pinned' => true, 'created_at' => now()->subHours(10)],
            );
        }

        // Chapter channel with a starter message.
        $channel = Conversation::query()->firstOrCreate(
            ['type' => 'channel', 'space_id' => $kanoSpace->id],
            [
                'title' => 'Kano Chapter channel',
                'created_by' => $hadiza?->id,
            ],
        );

        foreach ($users as $user) {
            if (! $channel->members()->where('users.id', $user->id)->exists()) {
                $channel->members()->attach($user->id);
            }
        }

        if ($hadiza && ! $channel->messages()->exists()) {
            Message::query()->create([
                'conversation_id' => $channel->id,
                'sender_id' => $hadiza->id,
                'body' => 'Chapter channel is open. Use this for official notices; keep private DMs for one-to-one matters.',
                'created_at' => now()->subHours(5),
            ]);
        }
    }

    /**
     * A quick poll from a proprietor and an open formal vote run by the
     * chapter executive (COM-4), so both voting flows can be tried.
     */
    private function seedPolls(Space $kanoSpace, User $hadiza, User $bashir): void
    {
        if (Post::query()->where('space_id', $kanoSpace->id)->where('type', 'poll')->exists()) {
            return;
        }

        $polls = app(PollService::class);

        $quick = Post::query()->create([
            'space_id' => $kanoSpace->id, 'author_id' => $bashir->id, 'type' => 'poll',
            'body' => 'Which day works best for the next chapter training on the claims process?',
            'last_activity_at' => now()->subHours(6),
        ]);
        $polls->create($quick, ['options' => ['Saturday morning', 'Saturday afternoon', 'Sunday afternoon'], 'closes_at' => now()->addDays(5)]);

        $formal = Post::query()->create([
            'space_id' => $kanoSpace->id, 'author_id' => $hadiza->id, 'type' => 'poll', 'pinned' => true, 'pinned_by' => $hadiza->id,
            'body' => "Motion: set Kano Chapter's share of 2027 dues at ₦7,500 per school, to fund the chapter legal desk.",
            'last_activity_at' => now()->subHours(2),
        ]);
        $polls->create($formal, ['options' => ['For', 'Against', 'Abstain'], 'formal' => true, 'closes_at' => now()->addDays(7)]);
    }

    /**
     * Forum threads with replies and a marked best answer, so the forums read
     * like a working community rather than empty boxes.
     *
     * @param  Collection<string, User>  $users
     */
    private function seedThreads(int $kanoSpaceId, int $nationalSpaceId, Collection $users): void
    {
        $who = fn (string $n) => $users['+23480000000'.$n] ?? null;

        $threads = [
            [$kanoSpaceId, 'fee-collection', '04', 'What reminder cadence works for you?', true,
                'We send reminders before the due date, on the day, then 7 / 14 / 30 days after.

Does anyone delay the first SMS until after the grace week?',
                [
                    ['03', 'We wait until day 3 after the due date. Parents told us the day-of SMS felt like pressure on payday week.', 6, true],
                    ['14', 'Same in Daura. A short Hausa message works better than English for many of our parents.', 3, false],
                ], 2],
            [$kanoSpaceId, 'legal-claims', '05', 'Does the claim pack need the signed fee agreement?', true,
                'A parent withdrew mid-term owing two terms. We have invoices and the reminder log but the admission form fee page was never signed. Can we still escalate?',
                [
                    ['08', 'Yes, you can still file. The invoices, the reminder log and proof the child attended carry the claim. Add the admission letter if it mentions fees.', 9, true],
                    ['09', 'Upload them all in Claims and the panel will tell you what is missing before the demand letter goes out.', 2, false],
                ], 3],
            [$kanoSpaceId, 'landlords-facilities', '04', 'Landlord wants a 40% rent increase at renewal', false,
                'Our lease on the Tarauni site ends in March and the landlord wants a 40% increase. Has anyone negotiated a phased increase?',
                [
                    ['14', 'We agreed 15% a year over three years in Daura, in writing, with a right of first refusal if he sells.', 4, false],
                ], 1],
            [$nationalSpaceId, 'government-regulation', '01', 'New approval renewal circular — what changes?', false,
                'The ministry circular on approval renewals is out. Summary: renewals now every three years, inspection booked online, and the fee is paid to the state revenue account only.',
                [
                    ['02', 'Kano State has confirmed the online booking opens next month. Chapters will share the link.', 5, false],
                    ['10', 'Katsina is following the same schedule.', 1, false],
                ], 5],
        ];

        foreach ($threads as [$spaceId, $slug, $author, $title, $question, $body, $replies, $daysAgo]) {
            $forum = Forum::query()->where('space_id', $spaceId)->where('slug', $slug)->first();
            $author = $who($author);
            if (! $forum || ! $author) {
                continue;
            }

            $thread = Thread::query()->firstOrCreate(
                ['forum_id' => $forum->id, 'title' => $title],
                ['author_id' => $author->id, 'body' => $body, 'is_question' => $question],
            );
            if ($thread->wasRecentlyCreated) {
                $thread->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();
            }

            if ($thread->replies()->exists()) {
                continue;
            }

            foreach ($replies as $i => [$replier, $text, $votes, $best]) {
                $replier = $who($replier);
                if (! $replier) {
                    continue;
                }
                $reply = $thread->replies()->create(['author_id' => $replier->id, 'body' => $text, 'votes_count' => $votes]);
                $reply->forceFill(['created_at' => now()->subDays($daysAgo)->addHours(3 * ($i + 1))])->saveQuietly();

                if ($best) {
                    $thread->forceFill(['best_comment_id' => $reply->id, 'answered_at' => $reply->created_at])->saveQuietly();
                }
            }

            $thread->forceFill(['last_activity_at' => now()->subDays($daysAgo)->addHours(3 * count($replies))])->saveQuietly();
        }
    }
}
