<?php

namespace Modules\Announcements\Seeders;

use App\Models\Chapter;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Announcements\Models\Election;
use Modules\Announcements\Models\Event;
use Modules\Announcements\Models\EventCheckin;
use Modules\Announcements\Models\EventRsvp;
use Modules\Announcements\Models\JobListing;
use Modules\Announcements\Models\NewsPost;

class AnnouncementsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->where('phone', '+2348000000001')->first()
            ?? User::query()->first();

        $kanoChapter = User::query()->where('phone', '+2348000000003')->first() ?? $author;
        $katsinaState = User::query()->where('phone', '+2348000000010')->first() ?? $author;

        if (! $author) {
            return;
        }

        $news = [
            [
                'slug' => 'welcome-to-napps-member-schools',
                'title' => 'Welcome to the NAPPS Member Schools platform',
                'excerpt' => 'One home for dues, fees, claims and community across every chapter.',
                'body' => "Proprietors can now apply for membership online, pay dues, and follow chapter and state updates from one place.\n\nParents are added by their chapter — they sign in with the phone number the chapter registered.",
                'published_at' => now()->subDays(12),
            ],
            [
                'slug' => 'kano-chapter-dues-reminder-march',
                'title' => 'Kano Chapter: annual dues reminder',
                'excerpt' => 'Schools still owing 2026 annual dues should pay before the chapter AGM.',
                'body' => "Dear proprietors,\n\nPlease clear outstanding NAPPS annual dues and the Kano State development levy before 31 March.\n\nPay from Membership → Dues in the app. Receipts are issued instantly for online payments.\n\nHadiza Ibrahim\nChapter Executive, Kano Chapter",
                'published_at' => now()->subDays(5),
                'author' => $kanoChapter,
            ],
            [
                'slug' => 'katsina-north-training-day',
                'title' => 'Katsina North training day for bursars',
                'excerpt' => 'Hands-on training on invoicing, reminders and transfer clearance.',
                'body' => "The Katsina North Chapter will host a bursar training day at the Daura secretariat.\n\nBring a phone with the NAPPS app installed. Data import from Excel will be demonstrated.",
                'published_at' => now()->subDays(2),
                'author' => $katsinaState,
            ],
            [
                'slug' => 'national-legal-panel-expanded',
                'title' => 'National legal panel expands to more states',
                'excerpt' => 'Approved barristers can now receive claim files from Kano and Katsina schools.',
                'body' => "NAPPS national has onboarded additional barristers to the legal panel.\n\nSchools with accounts more than 90 days overdue can escalate to a claim pack. Chapter mediation remains the preferred first step.",
                'published_at' => now()->subDay(),
            ],
            [
                'slug' => 'draft-platform-uptime-notice',
                'title' => 'Scheduled maintenance window (draft)',
                'excerpt' => 'Draft notice — not yet published.',
                'body' => 'We plan a short maintenance window next Sunday 02:00–04:00 WAT.',
                'published_at' => null,
            ],
        ];

        foreach ($news as $row) {
            NewsPost::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'title' => $row['title'],
                    'excerpt' => $row['excerpt'],
                    'body' => $row['body'],
                    'audience' => 'public',
                    'level' => 'national',
                    'author_id' => ($row['author'] ?? $author)->id,
                    'published_at' => $row['published_at'],
                ],
            );
        }

        $events = [
            [
                'slug' => 'kano-chapter-fee-collection-webinar',
                'title' => 'Fee collection webinar for Kano chapter schools',
                'kind' => 'webinar',
                'summary' => 'Practical session on term invoicing, reminders and arrears ageing.',
                'body' => 'Join the chapter executive and bursars for a live walkthrough of the fees module.',
                'online_url' => 'https://meet.example.com/napps-kano-fees',
                'starts_at' => now()->addDays(10)->setTime(16, 0),
                'ends_at' => now()->addDays(10)->setTime(17, 30),
                'author' => $kanoChapter,
            ],
            [
                'slug' => 'katsina-north-agm-2026',
                'title' => 'Katsina North Chapter AGM 2026',
                'kind' => 'agm',
                'summary' => 'Annual general meeting with one-school-one-vote elections.',
                'body' => 'Venue: Daura LGA hall. Agenda and candidate list will be published one week before.',
                'venue' => 'Daura LGA Hall',
                'starts_at' => now()->addDays(28)->setTime(10, 0),
                'ends_at' => now()->addDays(28)->setTime(15, 0),
                'author' => $katsinaState,
            ],
            [
                'slug' => 'national-proprietors-forum',
                'title' => 'National proprietors forum (hybrid)',
                'kind' => 'meeting',
                'summary' => 'Policy updates, debtor check consent, and platform roadmap.',
                'venue' => 'NAPPS National Secretariat, Abuja',
                'online_url' => 'https://meet.example.com/napps-national-forum',
                'starts_at' => now()->addDays(45)->setTime(9, 30),
                'ends_at' => now()->addDays(45)->setTime(13, 0),
            ],
            [
                'slug' => 'landlord-dispute-clinic',
                'title' => 'Landlord & lease clinic',
                'kind' => 'event',
                'summary' => 'Model lease walkthrough with the legal panel.',
                'venue' => 'Kano Chapter secretariat',
                'starts_at' => now()->addDays(16)->setTime(15, 0),
                'ends_at' => now()->addDays(16)->setTime(17, 0),
                'author' => $kanoChapter,
            ],
        ];

        foreach ($events as $row) {
            Event::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'title' => $row['title'],
                    'kind' => $row['kind'],
                    'summary' => $row['summary'],
                    'body' => $row['body'] ?? null,
                    'venue' => $row['venue'] ?? null,
                    'online_url' => $row['online_url'] ?? null,
                    'starts_at' => $row['starts_at'],
                    'ends_at' => $row['ends_at'],
                    'audience' => 'public',
                    'level' => 'national',
                    'author_id' => ($row['author'] ?? $author)->id,
                    'published_at' => now()->subDays(3),
                ],
            );
        }

        $jobs = [
            [
                'slug' => 'primary-teacher-al-ihsan',
                'title' => 'Primary class teacher',
                'school_name' => 'Al-Ihsan Model Academy',
                'location' => 'Kano, Nassarawa',
                'employment_type' => 'full_time',
                'contact_email' => 'jobs@alihsan.example',
                'contact_phone' => '+2348000000005',
                'summary' => 'Experienced primary teacher for Basic 3–5. TRCN preferred.',
                'body' => "Al-Ihsan Model Academy (NAPPS member) seeks a full-time primary teacher.\n\nRequirements:\n- NCE or B.Ed\n- TRCN registration preferred\n- Experience with mixed-ability classes",
                'closes_at' => now()->addMonth(),
            ],
            [
                'slug' => 'bursar-daura-model',
                'title' => 'School bursar',
                'school_name' => 'Daura Model International School',
                'location' => 'Daura, Katsina',
                'employment_type' => 'full_time',
                'contact_email' => 'admin@dauramodel.example',
                'contact_phone' => '+2348000000014',
                'summary' => 'Bursar familiar with fee collection, bank reconciliation and NAPPS dues.',
                'body' => "Responsible for term invoicing, parent receipts and monthly reports to the proprietor.\n\nMust be comfortable with phones and Excel.",
                'closes_at' => now()->addWeeks(3),
            ],
            [
                'slug' => 'secondary-science-kano-capital',
                'title' => 'Secondary science teacher (JS 1–3)',
                'school_name' => 'Kano Capital International School',
                'location' => 'Kano, Tarauni',
                'employment_type' => 'contract',
                'contact_email' => 'hr@kanocapital.example',
                'summary' => 'Part-year contract covering Basic Science and Introductory Technology.',
                'body' => 'Two terms remaining this session. Immediate start preferred.',
                'closes_at' => now()->addWeeks(2),
            ],
            [
                'slug' => 'security-officer-tarauni',
                'title' => 'School security officer',
                'school_name' => 'Tarauni Heritage Academy',
                'location' => 'Kano, Tarauni',
                'employment_type' => 'full_time',
                'contact_phone' => '+2348000000003',
                'summary' => 'Day-shift gate and premises security for a member primary/secondary school.',
                'body' => 'Must live within commuting distance of Unguwa Uku. References required.',
                'closes_at' => now()->addMonth(),
            ],
        ];

        foreach ($jobs as $row) {
            JobListing::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'title' => $row['title'],
                    'school_name' => $row['school_name'],
                    'location' => $row['location'],
                    'employment_type' => $row['employment_type'],
                    'contact_email' => $row['contact_email'] ?? null,
                    'contact_phone' => $row['contact_phone'] ?? null,
                    'summary' => $row['summary'],
                    'body' => $row['body'],
                    'author_id' => $author->id,
                    'published_at' => now()->subHours(random_int(2, 48)),
                    'closes_at' => $row['closes_at'],
                ],
            );
        }

        $this->engagement($author);
    }

    /**
     * Agenda and minutes, RSVPs, a past event with check-ins, and a Kano chapter election.
     */
    private function engagement(User $author): void
    {
        $agm = Event::query()->where('slug', 'katsina-north-agm-2026')->first();
        $agm?->forceFill(['agenda' => [
            ['time' => '10:00', 'title' => 'Registration and accreditation of schools'],
            ['time' => '10:30', 'title' => 'Opening prayer and welcome address'],
            ['time' => '11:00', 'title' => 'Chapter treasurer\'s report'],
            ['time' => '12:00', 'title' => 'Election of chapter officers (one school, one vote)'],
            ['time' => '14:30', 'title' => 'Any other business and closing'],
        ]])->save();

        // A finished meeting, with minutes and a QR check-in list.
        $past = Event::query()->updateOrCreate(
            ['slug' => 'kano-chapter-quarterly-meeting'],
            [
                'title' => 'Kano Chapter quarterly meeting',
                'kind' => 'meeting',
                'summary' => 'Dues, the new platform and the term calendar.',
                'venue' => 'Kano Chapter secretariat',
                'starts_at' => now()->subWeeks(3)->setTime(10, 0),
                'ends_at' => now()->subWeeks(3)->setTime(12, 30),
                'audience' => 'members',
                'level' => 'chapter',
                'author_id' => $author->id,
                'published_at' => now()->subWeeks(5),
                'agenda' => [
                    ['time' => '10:00', 'title' => 'Welcome and attendance'],
                    ['time' => '10:20', 'title' => 'Dues position and payment options'],
                    ['time' => '11:15', 'title' => 'Demonstration of the member platform'],
                    ['time' => '12:00', 'title' => 'Term calendar and AOB'],
                ],
                'minutes' => "Present: 24 proprietors and the chapter executive.\n\n1. Dues: members were reminded to pay annual dues online.\n2. Platform: proprietors will receive parent enrolment training next month.\n3. Next meeting: first Saturday of next term.",
            ],
        );

        $people = User::query()
            ->whereIn('phone', ['+2348000000004', '+2348000000005', '+2348000000014', '+2348000000003'])
            ->get()
            ->keyBy('phone');

        foreach ($people as $phone => $person) {
            EventRsvp::query()->updateOrCreate(
                ['event_id' => $past->id, 'user_id' => $person->id],
                ['status' => 'going'],
            );

            if ($phone !== '+2348000000014') {
                EventCheckin::query()->firstOrCreate(
                    ['event_id' => $past->id, 'user_id' => $person->id],
                    ['checked_in_at' => $past->starts_at->copy()->addMinutes(random_int(-20, 15)), 'checked_in_by' => $author->id],
                );
            }
        }

        foreach ($people->only(['+2348000000004', '+2348000000005']) as $person) {
            foreach (['kano-chapter-fee-collection-webinar' => 'going', 'landlord-dispute-clinic' => 'maybe'] as $slug => $status) {
                $event = Event::query()->where('slug', $slug)->first();

                if ($event) {
                    EventRsvp::query()->updateOrCreate(['event_id' => $event->id, 'user_id' => $person->id], ['status' => $status]);
                }
            }
        }

        // Kano Chapter officer election, open for voting.
        $kanoChapter = Chapter::query()->where('name', 'Kano Chapter')->first();
        $chapterExec = $people->get('+2348000000003') ?? $author;

        if (! $kanoChapter) {
            return;
        }

        $election = Election::query()->firstOrCreate(
            ['title' => 'Kano Chapter chairman 2026', 'chapter_id' => $kanoChapter->id],
            [
                'description' => 'Choose the chairman who will lead the chapter for the next two years. One vote per active member school.',
                'state_id' => $kanoChapter->state_id,
                'status' => Election::OPEN,
                'closes_at' => now()->addWeeks(2),
                'created_by' => $chapterExec->id,
            ],
        );

        if (! $election->candidates()->exists()) {
            $alIhsan = School::withoutGlobalScopes()->where('name', 'Al-Ihsan Model Academy')->first();
            $capital = School::withoutGlobalScopes()->where('name', 'Kano Capital International School')->first();

            $election->candidates()->createMany([
                ['name' => 'Alhaji Bashir Lawan', 'school_id' => $alIhsan?->id, 'statement' => 'Faster dues-to-service turnaround and a chapter legal clinic every term.', 'sort' => 1],
                ['name' => 'Hajiya Rabi Sule', 'school_id' => $capital?->id, 'statement' => 'Teacher training days in every LGA and fairer levies for small schools.', 'sort' => 2],
                ['name' => 'Malam Ibrahim Danladi', 'school_id' => null, 'statement' => 'A stronger voice for nursery and primary schools at the ministry.', 'sort' => 3],
            ]);
        }
    }
}
