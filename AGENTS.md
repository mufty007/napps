# NAPPS Member Schools Platform

Laravel 13 modular monolith for the National Association of Proprietors of
Private Schools: membership and dues, school fees and collections, debt claims,
records, leases, surveys, templates, announcements and a members-only community.

## Stack

PHP 8.4, Laravel 13, Blade + Alpine.js + Tailwind CSS 4 (Vite), MySQL 8 in
production (SQLite for tests/quick local runs). Fortify (2FA, password sign-in)
plus custom phone-OTP sign-in; Spatie permission/activitylog/medialibrary;
Horizon, Reverb, Scout, DomPDF, Laravel Excel; Pest, Larastan, Pint.

## Where things live

- `app/` - shared kernel: org hierarchy (State → Chapter → School), users,
  role assignments, active-role scoping, auth, SMS, UI kit, navigation.
- `modules/{Name}/` - domain modules; conventions in `modules/README.md`.
- `resources/views/components/ui/` - Blade UI kit (`<x-ui.button>`, `<x-ui.card>`,
  `<x-ui.input>`, `<x-ui.stat>`, `<x-ui.modal>`, ...). Layouts in
  `components/layouts/` (`app`, `auth`, `public`).
- `resources/js/motion.js` - `x-reveal`, `x-count-up`, `x-pop` (GSAP/Anime.js, lazy-loaded).

## Access model

- A user holds any number of `RoleAssignment`s: a Spatie role + a place in the
  org chain (`national`, `state:{id}`, `chapter:{id}`, `school:{id}`, `personal`).
- `App\Support\Scope\ActiveContext` resolves the active assignment (role switcher
  stores it in the session) and narrows queries: `School` via
  `SchoolVisibilityScope`, school-owned module models via `BelongsToSchool`.
- No authenticated user (console, queue, guest pages) = unrestricted system
  context. Jobs and public controllers must filter explicitly.
- Dotted abilities (`fees.invoices.manage`) resolve against the active role's
  permissions (`PermissionMatrix`). Executives, bursars and secretariat must have
  2FA (`two-factor.required`, part of the `member` middleware group).

## Commands

```sh
php artisan migrate:fresh --seed     # states, placeholder chapters, roles, secretariat (+ demo data locally)
vendor/bin/pest                      # tests
vendor/bin/pint                      # format
vendor/bin/phpstan analyse           # static analysis (level 5)
npm run dev / npm run build
```

On this Windows dev box, OPcache is enabled for the CLI and breaks phar tools;
prefix PHP tools with `php -d opcache.enable_cli=0` if Pint/Composer scripts hang
or fail.

## Conventions

- User-facing strings go through `__()` (English ships; Hausa, Yoruba, Igbo and
  Pidgin come later as JSON translations).
- Every screen must work at 360 px wide, with touch targets ≥ 44 px, and meet WCAG 2.1 AA.
- Motion: ≤ 300 ms, transform/opacity only, respect `prefers-reduced-motion`.
- Money and records changes are audit-logged.

## Plan

Build plan: `~/.cursor/plans/napps_platform_build_dc6ada49.plan.md` (phases 0-8).
Phase 0 (foundation) is done; next is Phase 1 (Membership & dues).
