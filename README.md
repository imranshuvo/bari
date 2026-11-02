# House Cost Planner

Track the cost of building a house with your team: **Budget → Agreed → Spent**.
Laravel 13 · Filament 5 · PostgreSQL · English and বাংলা.

## Run it locally

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate   # then set DB_* to your Postgres
php artisan migrate --seed                          # --seed adds demo data
php artisan serve                                   # http://127.0.0.1:8000
```

Demo logins (password `password`):

| Email | Role |
|---|---|
| owner@example.com | Owner of both demo projects |
| editor@example.com | Editor on "Our house in Dhaka" |
| viewer@example.com | Viewer (Bangla) on "Our house in Dhaka" |

## Deploy

`docker-compose.yml` runs everything: app, background worker, PostgreSQL, WhatsApp gateway and
nightly backups. **[docs/handbook.md](docs/handbook.md)** covers deploying to Coolify at
bari.imrankhanshuvo.com, everyday tasks, backups and restore, and troubleshooting.

## Accounts

Sign-up is by invitation only (`OPEN_REGISTRATION=false`). Owners invite people from
**Team**; the invitation link lets them create an account. To create one yourself:

```bash
php artisan user:create                  # asks for name, email, password and project
php artisan user:password someone@x.com  # new password, signs them out everywhere
```

## Notifications

**Project settings → Notifications**: a list of emails, WhatsApp and SMS numbers that get a
message whenever a cost is added, changed, marked paid or deleted (in English or Bangla).
Sending code: `app/Support/CostNotifier.php`, `app/Notifications/CostChanged.php`,
`app/Services/Waha.php`, `app/Services/Sms/`.

## Tests

```bash
php artisan test        # uses the house_cost_planner_test Postgres database
```

## Where things live

- `app/Services/ProjectFinance.php`: every money figure (paid, expected total, left)
- `app/Support/Money.php`: storing, parsing and formatting money (Bangla digits, lakh grouping)
- `app/Policies`: what Owners, Editors and Viewers may do
- `app/Filament`: screens (Overview, Spent, Budget, Agreed, Suppliers, Team, Activity)
- `lang/bn.json`: Bangla text (a test fails if a string is missing)
- `docs/superpowers/specs/`: the design
