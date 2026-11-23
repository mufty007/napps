# NAPPS Member Schools Platform

One web platform for the National Association of Proprietors of Private Schools
to register and verify member schools, collect dues, help schools bill parents
and recover unpaid fees, keep records, track leases and run a members-only
community, from chapter to national level.

See `AGENTS.md` for architecture and conventions and `modules/README.md` for
the module layout.

## Local setup

Requirements: PHP 8.4+, Composer 2, Node 22+. MySQL 8 and Redis are the
production targets; SQLite is enough to run locally.

```sh
composer install
cp .env.example .env          # then set DB_CONNECTION=sqlite for a quick start
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

With `APP_ENV=local` and `SMS_DRIVER=log`, sign-in codes are written to the log
and shown on the code-entry screen.

### Demo accounts (local seed)

The demo data centres on two chapters: **Kano Chapter** (Kano State) and
**Katsina North Chapter** (Katsina State, around Daura), with schools at every
membership stage, this year's dues and a levy per state/chapter, payments and
one suspended school.

| Phone | Name | Role |
| --- | --- | --- |
| 0800 000 0000 | NAPPS Secretariat | Secretariat (from `SECRETARIAT_PHONE`) |
| 0800 000 0001 | Hajiya Zainab Abubakar | National executive |
| 0800 000 0002 | Aminu Sani Bello | State executive, Kano |
| 0800 000 0003 | Hadiza Ibrahim | Chapter executive, Kano Chapter (1 school to verify) |
| 0800 000 0004 | Alhaji Bashir Lawan | Proprietor of Al-Ihsan Model Academy and Kano Capital International School (owing) + chapter executive, Kano (role switcher) |
| 0800 000 0005 | Fatima Yusuf | Bursar, Al-Ihsan Model Academy |
| 0800 000 0006 | Ibrahim Musa | Staff, Al-Ihsan Model Academy |
| 0800 000 0007 | Aisha Garba | Parent (enrolled by Kano Chapter — parents cannot self-register) |
| 0800 000 0008 | Barr. Yusuf Lawal | Legal panel |
| 0800 000 0009 | Kabiru Abdullahi | Community moderator, Kano Chapter |
| 0800 000 0010 | Usman Dikko | State executive, Katsina (1 school to approve) |
| 0800 000 0011 | Maryam Sani | Chapter executive, Katsina North Chapter |
| 0800 000 0012 | Sadiq Umar | Applicant, no role yet (Gwale Excellence, awaiting Kano Chapter) |
| 0800 000 0013 | Halima Bello | Applicant, no role yet (Zango Bright Stars, awaiting Katsina State) |
| 0800 000 0014 | Nasiru Ahmed | Proprietor, Daura Model International School |

Public hubs: `/news`, `/events`, `/jobs`, `/join` (school registration by
state → chapter). Chapter executives add parents under **Membership → Parents**.

Payments run against a simulated checkout locally (`PAYMENTS_DRIVER=fake`):
"Pay online" opens a test page where you choose the outcome. Issuing dues
runs on the queue, so keep a worker running (`php artisan queue:work`) or set
`QUEUE_CONNECTION=sync` while clicking around.

Demo accounts also accept the password `password`. Locally `TWO_FACTOR_ENFORCE`
defaults to `false` so executives and bursars can click through without setting
up 2FA (always enforced in production).

## Quality checks

```sh
vendor/bin/pest
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

CI runs all three on every pull request, plus the test suite against MySQL 8.
