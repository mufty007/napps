<?php

namespace Database\Seeders;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\Chapter;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-only sample data: a few schools and one account per role.
 * Sign in with the phone number (the SMS code is shown on screen locally)
 * or with the password "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $ikeja = Chapter::query()->where('name', 'Ikeja Chapter')->firstOrFail();
        $abuja = Chapter::query()->where('name', 'Abuja Chapter')->firstOrFail();
        $kano = Chapter::query()->where('name', 'Kano Chapter')->firstOrFail();

        $schools = collect([
            ['Bright Future Academy', $ikeja, MembershipStatus::Active, ['nursery', 'primary']],
            ['Royal Crest International School', $ikeja, MembershipStatus::Owing, ['primary', 'secondary']],
            ['Greenfield Nursery & Primary School', $ikeja, MembershipStatus::Pending, ['nursery', 'primary']],
            ['Unity College', $abuja, MembershipStatus::Active, ['secondary']],
            ['Heritage Schools', $abuja, MembershipStatus::Suspended, ['nursery', 'primary', 'secondary']],
            ['Morning Star Academy', $kano, MembershipStatus::Active, ['primary']],
        ])->mapWithKeys(fn ($s) => [$s[0] => School::withoutGlobalScopes()->firstOrCreate(['name' => $s[0]], [
            'chapter_id' => $s[1]->id,
            'membership_status' => $s[2],
            'levels' => $s[3],
            'pupil_count' => random_int(80, 900),
            'address' => 'Demo address, '.$s[1]->lga,
        ])]);

        $brightFuture = $schools['Bright Future Academy'];
        $royalCrest = $schools['Royal Crest International School'];

        $accounts = [
            ['+2348000000001', 'Ngozi Okafor', [[Role::NationalExecutive, ScopeLevel::National, null]]],
            ['+2348000000002', 'Tunde Bakare', [[Role::StateExecutive, ScopeLevel::State, $ikeja->state_id]]],
            ['+2348000000003', 'Aisha Bello', [[Role::ChapterExecutive, ScopeLevel::Chapter, $ikeja->id]]],
            ['+2348000000004', 'Emeka Nwosu', [
                [Role::Proprietor, ScopeLevel::School, $brightFuture->id],
                [Role::Proprietor, ScopeLevel::School, $royalCrest->id],
                [Role::ChapterExecutive, ScopeLevel::Chapter, $ikeja->id],
            ]],
            ['+2348000000005', 'Funke Adeyemi', [[Role::Bursar, ScopeLevel::School, $brightFuture->id]]],
            ['+2348000000006', 'Ibrahim Musa', [[Role::Staff, ScopeLevel::School, $brightFuture->id]]],
            ['+2348000000007', 'Chioma Eze', [[Role::Parent, ScopeLevel::Personal, null]]],
            ['+2348000000008', 'Barr. Yusuf Lawal', [[Role::LegalPanel, ScopeLevel::Personal, null]]],
            ['+2348000000009', 'Kemi Alade', [[Role::Moderator, ScopeLevel::Chapter, $ikeja->id]]],
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
        }

        $this->command?->table(['Phone', 'Name', 'Role'], $rows);
        $this->command?->info('Demo accounts: sign in by phone (code shown on screen locally) or password "password".');
    }
}
