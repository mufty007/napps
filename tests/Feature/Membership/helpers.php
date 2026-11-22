<?php

use App\Contracts\SmsGateway;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Enums\ScopeLevel;
use App\Models\Chapter;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Modules\Membership\Enums\DuesKind;
use Modules\Membership\Models\DuesCharge;
use Modules\Membership\Services\DuesLedger;

/**
 * Shared set-up for the Membership feature tests (loaded from tests/Pest.php).
 */
function membershipOrg(): array
{
    $kano = State::factory()->create(['name' => 'Kano', 'code' => 'KN']);
    $katsina = State::factory()->create(['name' => 'Katsina', 'code' => 'KT']);

    return [
        'kano' => $kano,
        'katsina' => $katsina,
        'kanoChapter' => Chapter::factory()->for($kano)->create(['name' => 'Kano Chapter']),
        'katsinaNorth' => Chapter::factory()->for($katsina)->create(['name' => 'Katsina North Chapter']),
    ];
}

function officer(Role $role, ?int $scopeId = null, array $attributes = []): User
{
    $user = User::factory()->withTwoFactor()->create($attributes);
    RoleAssignment::grant($user, $role, $role->allowedLevels()[0], $scopeId);

    return $user;
}

function memberSchool(Chapter $chapter, array $attributes = [], ?User $proprietor = null): School
{
    $school = School::factory()->for($chapter)->create([
        'membership_status' => MembershipStatus::Active,
        'membership_number' => null,
        ...$attributes,
    ]);

    if ($proprietor) {
        RoleAssignment::grant($proprietor, Role::Proprietor, ScopeLevel::School, $school->id);
    }

    return $school;
}

function issuedCharge(array $attributes = []): DuesCharge
{
    $charge = DuesCharge::query()->create([
        'scope_type' => ScopeLevel::National,
        'scope_id' => null,
        'kind' => DuesKind::Annual,
        'title' => 'Annual dues '.now()->year,
        'year' => now()->year,
        'amount_kobo' => 2_500_000,
        'due_date' => now()->subMonth()->toDateString(),
        'share_national' => 100,
        'issued_at' => now(),
        ...$attributes,
    ]);

    app(DuesLedger::class)->bill($charge);

    return $charge;
}

function fakePdf(string $name = 'document.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF");
}

/**
 * @return list<string>
 */
function smsTo(User $user): array
{
    return collect(app(SmsGateway::class)->sent)->where('to', $user->phone)->pluck('message')->values()->all();
}
