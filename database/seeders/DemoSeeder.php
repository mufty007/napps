<?php

namespace Database\Seeders;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\Chapter;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\State;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Announcements\Seeders\AnnouncementsDemoSeeder;
use Modules\Claims\Seeders\ClaimsDemoSeeder;
use Modules\Community\Seeders\CommunityDemoSeeder;
use Modules\Fees\Seeders\FeesDemoSeeder;
use Modules\Membership\Models\ChapterParent;
use Modules\Membership\Seeders\MembershipDemoSeeder;
use Modules\Surveys\Seeders\SurveysDemoSeeder;
use Modules\Templates\Seeders\TemplatesDemoSeeder;

/**
 * Local-only sample data, centred on two chapters: Kano Chapter (Kano State)
 * and Katsina North Chapter (Katsina State, around Daura).
 *
 * Sign in with the phone number (the SMS code is shown on screen locally)
 * or with the password "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $kano = Chapter::query()->where('name', 'Kano Chapter')->firstOrFail();
        $katsina = State::query()->where('code', 'KT')->firstOrFail();
        $katsinaNorth = Chapter::query()->firstOrCreate(
            ['state_id' => $katsina->id, 'name' => 'Katsina North Chapter'],
            ['lga' => 'Daura'],
        );

        // [name, chapter, status, levels, lga, address, pupils, lat, lng]
        $schools = collect([
            ['Al-Ihsan Model Academy', $kano, MembershipStatus::Active, ['nursery', 'primary'], 'Nassarawa', '12 Zaria Road, Gyadi-Gyadi', 640, 11.9719, 8.5311],
            ['Kano Capital International School', $kano, MembershipStatus::Active, ['primary', 'secondary'], 'Tarauni', 'Plot 4, Maiduguri Road', 910, 11.9632, 8.5480],
            ['Tarauni Heritage Academy', $kano, MembershipStatus::Active, ['nursery', 'primary', 'secondary'], 'Tarauni', '7 Hadejia Road, Unguwa Uku', 455, 11.9580, 8.5395],
            ['Sabon Gari Unity College', $kano, MembershipStatus::Active, ['secondary'], 'Fagge', '23 Enugu Road, Sabon Gari', 380, 12.0110, 8.5300],
            ['Gwale Excellence Nursery & Primary School', $kano, MembershipStatus::Pending, ['nursery', 'primary'], 'Gwale', '5 Gwale Market Road', 210, 11.9905, 8.4930],
            ['Daura Model International School', $katsinaNorth, MembershipStatus::Active, ['nursery', 'primary', 'secondary'], 'Daura', 'Emir\'s Palace Road, Daura', 520, 13.0360, 8.3180],
            ['Mai\'adua Pinnacle Academy', $katsinaNorth, MembershipStatus::Active, ['primary'], 'Mai\'adua', 'Kano Road, Mai\'adua', 260, 13.1890, 8.2140],
            ['Zango Bright Stars Schools', $katsinaNorth, MembershipStatus::Pending, ['nursery', 'primary'], 'Zango', 'Opposite LGA Secretariat, Zango', 190, 12.9480, 8.5300],
        ])->mapWithKeys(fn ($s) => [$s[0] => School::withoutGlobalScopes()->firstOrCreate(['name' => $s[0]], [
            'chapter_id' => $s[1]->id,
            'membership_status' => $s[2],
            'levels' => $s[3],
            'lga' => $s[4],
            'address' => $s[5],
            'pupil_count' => $s[6],
            'latitude' => $s[7],
            'longitude' => $s[8],
            'registration_number' => 'RC'.(1_200_000 + crc32($s[0]) % 800_000),
            'ministry_approval_number' => $s[2] === MembershipStatus::Pending ? null : 'MOE/'.($s[1]->is($kano) ? 'KN' : 'KT').'/'.(1000 + crc32($s[0]) % 9000),
            'phone' => '+23480'.str_pad((string) (crc32($s[0]) % 100_000_000), 8, '0', STR_PAD_LEFT),
        ])]);

        $alIhsan = $schools['Al-Ihsan Model Academy'];

        $accounts = [
            ['+2348000000001', 'Hajiya Zainab Abubakar', [[Role::NationalExecutive, ScopeLevel::National, null]]],
            ['+2348000000002', 'Aminu Sani Bello', [[Role::StateExecutive, ScopeLevel::State, $kano->state_id]]],
            ['+2348000000003', 'Hadiza Ibrahim', [[Role::ChapterExecutive, ScopeLevel::Chapter, $kano->id]]],
            ['+2348000000004', 'Alhaji Bashir Lawan', [
                [Role::Proprietor, ScopeLevel::School, $alIhsan->id],
                [Role::Proprietor, ScopeLevel::School, $schools['Kano Capital International School']->id],
                [Role::ChapterExecutive, ScopeLevel::Chapter, $kano->id],
            ]],
            ['+2348000000005', 'Fatima Yusuf', [[Role::Bursar, ScopeLevel::School, $alIhsan->id]]],
            ['+2348000000006', 'Ibrahim Musa', [[Role::Staff, ScopeLevel::School, $alIhsan->id]]],
            ['+2348000000007', 'Aisha Garba', [[Role::Parent, ScopeLevel::Personal, null]]],
            ['+2348000000008', 'Barr. Yusuf Lawal', [[Role::LegalPanel, ScopeLevel::Personal, null]]],
            ['+2348000000009', 'Kabiru Abdullahi', [[Role::Moderator, ScopeLevel::Chapter, $kano->id]]],
            ['+2348000000010', 'Usman Dikko', [[Role::StateExecutive, ScopeLevel::State, $katsina->id]]],
            ['+2348000000011', 'Maryam Sani', [[Role::ChapterExecutive, ScopeLevel::Chapter, $katsinaNorth->id]]],
            ['+2348000000012', 'Sadiq Umar', []], // applicant: Gwale Excellence
            ['+2348000000013', 'Halima Bello', []], // applicant: Zango Bright Stars
            ['+2348000000014', 'Nasiru Ahmed', [[Role::Proprietor, ScopeLevel::School, $schools['Daura Model International School']->id]]],
        ];

        $rows = [];

        foreach ($accounts as [$phone, $name, $roles]) {
            $user = User::query()->firstOrCreate(['phone' => $phone], [
                'name' => $name,
                'password' => Hash::make('password'),
            ]);
            $user->forceFill(['phone_verified_at' => now()])->saveQuietly();

            foreach ($roles as [$role, $level, $scopeId]) {
                $assignment = RoleAssignment::grant($user, $role, $level, $scopeId);
                $rows[] = [PhoneNumber::format($phone), $name, $assignment->label()];
            }

            if ($roles === []) {
                $rows[] = [PhoneNumber::format($phone), $name, 'Applicant (no role yet)'];
            }
        }

        // Demo parent linked to Kano chapter (parents are chapter-enrolled, not self-registered).
        $parent = User::query()->where('phone', '+2348000000007')->first();
        if ($parent) {
            ChapterParent::query()->firstOrCreate(
                ['chapter_id' => $kano->id, 'user_id' => $parent->id],
                ['added_by' => User::query()->where('phone', '+2348000000003')->value('id')],
            );
        }

        if (class_exists(MembershipDemoSeeder::class)) {
            $this->call(MembershipDemoSeeder::class);
        }

        if (class_exists(AnnouncementsDemoSeeder::class)) {
            $this->call(AnnouncementsDemoSeeder::class);
        }

        if (class_exists(FeesDemoSeeder::class)) {
            $this->call(FeesDemoSeeder::class);
        }

        if (class_exists(ClaimsDemoSeeder::class)) {
            $this->call(ClaimsDemoSeeder::class);
        }

        if (class_exists(CommunityDemoSeeder::class)) {
            $this->call(CommunityDemoSeeder::class);
        }

        if (class_exists(TemplatesDemoSeeder::class)) {
            $this->call(TemplatesDemoSeeder::class);
        }

        if (class_exists(SurveysDemoSeeder::class)) {
            $this->call(SurveysDemoSeeder::class);
        }

        $this->command?->table(['Phone', 'Name', 'Role'], $rows);
        $this->command?->info('Demo accounts: sign in by phone (code shown on screen locally) or password "password".');
        $this->command?->info('2FA is off locally (TWO_FACTOR_ENFORCE=false). Parents are enrolled by chapter executives.');
    }
}
