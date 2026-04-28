# Mobile2 Post-Apply Runbook

All 36 patches from `trivartatech/mobile2` are committed in 8 wave commits on `main`.
Baseline tag: `pre-mobile2-patches` (HEAD before any patch).

This runbook covers everything that still has to happen on the **build/runtime host**
(the machine where `vendor/` and `node_modules/` live and where `php artisan migrate`
will actually touch the database).

---

## 1. Sync this commit to the build host

If you build in `C:/laragon/www/...` or on a remote, pull `main` (or copy the working
tree) so the build host has commits up to and including the Wave 8 commit.

```powershell
git pull --ff-only origin main
```

The wave commits are:

| Wave | Commit  | Message                                                       |
|:----:|---------|---------------------------------------------------------------|
| 1    | `2950a58` | apply wave 1 (patches 0001-0005)                              |
| 2    | (auto)    | apply wave 2 (patches 0006-0012)                              |
| 3    | `a6ad4d8` | apply wave 3 (patches 0013-0015)                              |
| 4    | `f0381c6` | apply wave 4 (patches 0016-0018)                              |
| 5    | `8672c01` | apply wave 5 (patches 0019-0021)                              |
| 6    | `95e1d1a` | apply wave 6 (patches 0022-0024)                              |
| 7    | (auto)    | apply wave 7 (patches 0025-0027)                              |
| 8    | (auto)    | apply wave 8 (patches 0028-0036)                              |

(Hashes may differ slightly on your end after a fast-forward — what matters is the order.)

---

## 2. Install / refresh dependencies

```powershell
# from project root
composer install --no-interaction --prefer-dist --optimize-autoloader
npm install --no-audit --no-fund
```

No new composer packages are added by the patches; `composer install` is only here in
case `vendor/` is missing or out of date. `kreait/firebase-php` is already in
`composer.json`, so FCM remains supported.

---

## 3. Run database migrations

Three new migrations were added across waves 5/7/8:

| Migration                                                            | Wave | Purpose                                                                        |
|---|:--:|---|
| `2026_04_27_114325_add_holiday_to_attendance_status_enum.php`        | 5    | Adds `'holiday'` to `attendances.status` enum (driver-aware MySQL/SQLite ALTER) |
| `database/migrations/...add_qr_token_to_users.php` (look in commit 7)| 7    | Adds `qr_token` column to `users` for staff/student QR badges                  |
| `2026_04_27_150000_add_expo_push_token_to_users.php`                 | 8    | Adds `expo_push_token`, `device_platform`, `push_token_updated_at` to `users` |

Run:

```powershell
php artisan migrate --force
```

If your prod target uses a custom MySQL collation, the enum change in
`add_holiday_to_attendance_status_enum.php` does a `DB::statement` driver-aware ALTER —
inspect once before pushing to production.

---

## 4. Rebuild Vue assets

Touched by waves 2, 3, 4, 7, 8 — full rebuild covers all of them at once:

```powershell
npm run build
```

If anything fails to compile, the most likely culprit is a custom page that was
previously importing dates / formatters by hand and has been swept by patch 0014's
`useFormat()` composable. The composable lives at
`resources/js/Composables/useFormat.js`.

---

## 5. Cache clear + route/config cache

```powershell
php artisan optimize:clear
php artisan route:cache
php artisan config:cache
```

Skip the README's `systemctl reload php8.3-fpm` line on dev / Windows; on Linux prod
it stays.

---

## 6. Smoke verification (per role)

Generate bearer tokens from tinker:

```powershell
php artisan tinker --execute="`$u = App\Models\User::where('email','EMAIL_HERE')->first(); echo 'BEARER='.$u->createToken('debug')->plainTextToken.PHP_EOL;"
```

Then hit:

```powershell
# Teacher — flattened exam papers (patch 0030)
curl -H "Authorization: Bearer <T>" -H "Accept: application/json" https://<host>/api/mobile/exams

# Driver — assigned vehicles + route + stops (patches 0033/0035)
curl -H "Authorization: Bearer <T>" -H "Accept: application/json" https://<host>/api/mobile/transport/driver/vehicles

# Gate keeper — gate stats (patch 0036)
curl -H "Authorization: Bearer <T>" -H "Accept: application/json" https://<host>/api/mobile/front-office/gate/stats

# Parent — child list with attendance_pct (patch 0004)
curl -H "Authorization: Bearer <T>" -H "Accept: application/json" https://<host>/api/mobile/children

# Push token registration (patch 0029)
curl -X POST -H "Authorization: Bearer <T>" -H "Content-Type: application/json" \
  -d '{"expo_push_token":"ExponentPushToken[xxxxx]","device_platform":"android"}' \
  https://<host>/api/mobile/device/register
```

UI checks:

- **User Management** → "Create Missing Logins", "Bulk Reset", "Export Credentials" buttons present; class/section filter works for parents.
- **Communication Templates** → delete button gone for system templates (slugs `fee_due`, `diary_created`, `assignment_created`, etc.).
- **Students list** → "QR Badges PDF" + "QR Excel" buttons appear; defaulter filter still works alongside.
- **Due Report** → "Send All Reminders" + per-row "📣 Remind" buttons; rich row layout (class, contacts, fee/transport columns, balance) preserved.

---

## 7. Rollback (if needed)

Full rollback to the pre-patch baseline:

```powershell
git reset --hard pre-mobile2-patches
php artisan migrate:rollback --step=3   # the 3 new migrations from waves 5/7/8
php artisan optimize:clear
npm run build
```

Single-wave rollback (e.g., wave 8 only):

```powershell
git revert <wave-8-commit>
php artisan migrate:rollback --step=1   # only if that wave's migration was already run
```

---

## 8. Conflicts that were resolved manually during apply

Recorded for traceability — already in the wave commits:

| File                                              | Wave | What was preserved                                                       |
|---|:--:|---|
| `app/Http/Controllers/School/UserManagementController.php` | 2 | Kept patch's classes/sections/missing_counts shape (uses `sort_order`)   |
| `resources/js/Pages/School/Students/Index.vue`            | 7 | Kept custom defaulter filter + added new QR Badges PDF button alongside   |
| `resources/js/Pages/School/Finance/Ledger/DueReport.vue`  | 8 | Kept rich row layout + custom defaulter columns + GL re-coupling; added new Send Reminder buttons (single + bulk); fixed `student.` → `row.` reference in patch's button |
