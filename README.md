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

| Phone | Role |
| --- | --- |
| 0800 000 0000 | Secretariat (from `SECRETARIAT_PHONE`) |
| 0800 000 0001 | National executive |
| 0800 000 0002 | State executive, Lagos |
| 0800 000 0003 | Chapter executive, Ikeja |
| 0800 000 0004 | Proprietor of two schools + chapter executive (role switcher) |
| 0800 000 0005 | Bursar, Bright Future Academy |
| 0800 000 0006 | Staff, Bright Future Academy |
| 0800 000 0007 | Parent |
| 0800 000 0008 | Legal panel |
| 0800 000 0009 | Community moderator, Ikeja |

Demo accounts also accept the password `password`. Executives, bursars and the
secretariat must set up two-factor authentication before they reach their
dashboard; set `TWO_FACTOR_ENFORCE=false` locally to skip that while clicking
around (it is always enforced in production).

## Quality checks

```sh
vendor/bin/pest
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

CI runs all three on every pull request, plus the test suite against MySQL 8.
