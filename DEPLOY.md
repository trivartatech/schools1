# Deploy a New School

Generic school ERP deployment — clone, configure, run one command.

## Prerequisites

- Linux server (Ubuntu / cPanel / CloudPanel)
- PHP 8.2+, Composer, Node 18+, npm, MySQL 8+
- Domain + DNS pointing at this server
- An empty MySQL database (or root creds for `bootstrap.sh` to create it)

## Fresh install — pick ONE path

### Path A — Friendly Excel config *(recommended for school admins)*

```bash
git clone https://github.com/trivartatech/schools1.git myschool
cd myschool

cp school-setup.example.xlsx school-setup.xlsx
# Open school-setup.xlsx in Excel/LibreOffice/Google Sheets.
# Fill the Value column on all 3 sheets (School / Database / Mail & Integrations).
# Save (keep .xlsx format).

chmod +x bootstrap.sh deploy.sh
./bootstrap.sh        # auto-detects xlsx → generates .env → installs everything
```

### Path B — Edit `.env` directly *(technical users)*

```bash
git clone https://github.com/trivartatech/schools1.git myschool
cd myschool

cp .env.production.example .env
nano .env             # fill DB_*, APP_URL, SCHOOL_*, ORG_*, *_EMAIL

chmod +x bootstrap.sh deploy.sh
./bootstrap.sh
```

> Both paths produce the same `.env`. The Excel path uses
> `php artisan school:configure-from-xlsx` (powered by `maatwebsite/excel`)
> to convert the spreadsheet into `.env` before standard install begins.

## What `bootstrap.sh` does

1. Validates `.env` (or generates it from `school-setup.xlsx`)
2. *(optional)* Creates the MySQL database + user if `DB_ROOT_*` was filled
3. `composer install --no-dev --optimize-autoloader`
4. Generates `APP_KEY` if missing
5. Creates storage symlink
6. Clears stale config cache
7. Runs all migrations
8. Seeds **roles & permissions → school → grading → communication templates**
9. `npm install` + `npm run build` (skipped if `public/build/` already exists)
10. Caches config/routes/views/events
11. Sets directory permissions on `storage/` and `bootstrap/cache/`
12. Writes `.bootstrap-done` so re-running prints a friendly "use ./deploy.sh" hint

## Subsequent updates

```bash
./deploy.sh           # git pull + mysqldump backup + migrate + cache + queue restart
```

`deploy.sh` is safe to re-run — it always backs up the DB to `storage/backups/` before migrating.

## Default credentials (change on first login!)

After bootstrap, log in with the emails set in `.env`/xlsx and password from `DEFAULT_PASSWORD` (default `ChangeMe@2026`):

- `superadmin@yourschool.com` — Super Admin (multi-school root)
- `admin@yourschool.com` — School Admin
- `principal@yourschool.com` — Principal

## Post-deploy checklist

After first login, the school admin should:

1. Change all 3 default passwords
2. Configure SMS / WhatsApp / Voice gateways → **Settings → Communication Providers**
3. Upload the school logo + favicon → **Settings → Branding**
4. Set up cron and queue worker on the server (commands printed at the end of `bootstrap.sh`)

## Notes & gotchas

- **MySQL passwords**: avoid `#`, `$`, `!`, `\`, `'` — these break bash `source .env`. Stick to alphanumerics plus `@-_.=+`.
- **Re-bootstrap**: `bootstrap.sh` refuses to run twice. Delete `.bootstrap-done` to force.
- **Backups**: `deploy.sh` runs `mysqldump` to `storage/backups/` before each migration; clean up old gz files periodically.
- **CloudPanel**: most CloudPanel installs don't expose MySQL root over the network. Create the DB+user via CloudPanel's UI, leave `Root User` / `Root Password` blank in the xlsx, then run `./bootstrap.sh`.
- **Re-generating the Excel template**: `php artisan school:configure-from-xlsx --init` writes a fresh `school-setup.example.xlsx` from the current `.env.production.example` defaults.
