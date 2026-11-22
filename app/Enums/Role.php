<?php

namespace App\Enums;

/**
 * The platform's roles (PRD: Users and roles). Values are the Spatie role names.
 */
enum Role: string
{
    case Secretariat = 'secretariat';
    case NationalExecutive = 'national_executive';
    case StateExecutive = 'state_executive';
    case ChapterExecutive = 'chapter_executive';
    case Proprietor = 'proprietor';
    case Bursar = 'bursar';
    case Staff = 'staff';
    case Parent = 'parent';
    case LegalPanel = 'legal_panel';
    case Moderator = 'moderator';

    public function label(): string
    {
        return match ($this) {
            self::Secretariat => __('Secretariat'),
            self::NationalExecutive => __('National executive'),
            self::StateExecutive => __('State executive'),
            self::ChapterExecutive => __('Chapter executive'),
            self::Proprietor => __('Proprietor'),
            self::Bursar => __('Bursar'),
            self::Staff => __('Staff'),
            self::Parent => __('Parent'),
            self::LegalPanel => __('Legal panel'),
            self::Moderator => __('Community moderator'),
        };
    }

    /**
     * The org levels this role may be assigned at.
     *
     * @return list<ScopeLevel>
     */
    public function allowedLevels(): array
    {
        return match ($this) {
            self::Secretariat, self::NationalExecutive => [ScopeLevel::National],
            self::StateExecutive => [ScopeLevel::State],
            self::ChapterExecutive => [ScopeLevel::Chapter],
            self::Proprietor, self::Bursar, self::Staff => [ScopeLevel::School],
            self::Parent, self::LegalPanel => [ScopeLevel::Personal],
            self::Moderator => [ScopeLevel::National, ScopeLevel::State, ScopeLevel::Chapter],
        };
    }

    /**
     * PRD: two-factor authentication is required for executives and bursars.
     * Secretariat staff hold platform-wide admin rights, so they are included.
     */
    public function requiresTwoFactor(): bool
    {
        return in_array($this, [
            self::Secretariat,
            self::NationalExecutive,
            self::StateExecutive,
            self::ChapterExecutive,
            self::Bursar,
        ], true);
    }

    /**
     * Which app shell (navigation set) the role lands in.
     */
    public function shell(): string
    {
        return match ($this) {
            self::Secretariat => 'secretariat',
            self::NationalExecutive, self::StateExecutive, self::ChapterExecutive, self::Moderator => 'executive',
            self::Proprietor, self::Bursar, self::Staff => 'school',
            self::Parent => 'parent',
            self::LegalPanel => 'legal',
        };
    }

    /**
     * Parents never get the members-only community (PRD: Key screens).
     */
    public function hasCommunityAccess(): bool
    {
        return $this !== self::Parent;
    }

    /**
     * Ordering used when picking a default role for a user with several.
     */
    public function priority(): int
    {
        return match ($this) {
            self::Secretariat => 10,
            self::NationalExecutive => 20,
            self::StateExecutive => 30,
            self::ChapterExecutive => 40,
            self::Proprietor => 50,
            self::Bursar => 60,
            self::Staff => 70,
            self::Moderator => 80,
            self::LegalPanel => 90,
            self::Parent => 100,
        };
    }
}
