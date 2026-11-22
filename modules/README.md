# Domain modules

The platform is a modular monolith: one Laravel app, ten domain modules, and a
shared kernel in `app/`.

| Module | Scope (PRD) | Build phase |
| --- | --- | --- |
| Membership | Registry, onboarding → certificate + QR, dues & split, compliance, public verify | 1 |
| Fees | Fee structures, bulk invoices, Paystack, virtual accounts, reminders, arrears, parent portal, agreements | 2 |
| Records | Students, guardians, staff, results, transfer clearance, debtor check, retention | 3 |
| Templates | Versioned, legally reviewed document library, fill-in → PDF | 4 |
| Claims | Claim packs, case pipeline, mediation, legal panel, per-state enablement | 4 |
| Leases | Lease register, rent log, renewal reminders, vault, disputes, model lease | 5 |
| Surveys | Builder, targeting, reminders, results by chapter/state, needs assessment | 6 |
| Announcements | Circulars, events, RSVP/QR check-in, elections, directory, benefits | 6 |
| Community | Feed, forums, groups, chat (Reverb), polls/votes, moderation | 7 |
| Platform | Search, analytics, secretariat console, imports, PWA/offline, NDPA tooling | 8 |

## Layout

```
modules/Fees/
  Providers/FeesServiceProvider.php   extends App\Support\Modules\ModuleServiceProvider
  Models/  Policies/  Http/  Jobs/  Notifications/
  routes/web.php                      loaded inside the "web" middleware group
  resources/views/                    view namespace "fees::"
  database/migrations/                picked up by `php artisan migrate`
  lang/                               JSON translations
```

A module is switched on by listing it in `config/modules.php`. Its provider can
override `navigation()` to add main screens (`App\Support\Navigation\NavItem`)
and `bootModule()` for policies, listeners and schedules.

## Rules

- **Scope every school-owned table.** Models with a `school_id` column use
  `App\Models\Concerns\BelongsToSchool`, which narrows queries to the active
  role's State → Chapter → School scope. Parents and panel lawyers see nothing
  through this scope; give them explicit, linked access paths.
- **Authorise with permissions + policies.** Abilities named
  `module.resource.action` are checked against the active role
  (`@can('fees.invoices.manage')`). Add new ones to
  `App\Support\Authorization\PermissionMatrix` and re-run
  `php artisan db:seed --class=RolesAndPermissionsSeeder`.
- **Signed-in routes use the `member` middleware group** (auth + 2FA where the
  role requires it).
- **Log money, records, roles and moderation** with `LogsActivity` or `activity()`.
- **No reaching into another module's tables.** Talk through events or small
  contracts in `app/Contracts`. Community never reads fees or student data.
- **Take online payments through the kernel.** Call
  `App\Services\Payments\PaymentService::start()` with a `purpose` and listen for
  `App\Events\PaymentConfirmed` (it fires once, inside a transaction) to record
  the money. See `Membership\Listeners\RecordOnlineDuesPayment`.
- **Add dashboard cards, not dashboard code.** Register views with
  `App\Support\Dashboard\DashboardWidgets` and feed them with a view composer.
- **Tell Larastan about module tables.** Add `modules/{Name}/database/migrations`
  to `databaseMigrationsPath` in `phpstan.neon`.
- **Keep pages light** (PRD: < 500 KB on 3G). Load heavy JS on demand, as
  `resources/js/motion.js` and `connectEcho()` do.
