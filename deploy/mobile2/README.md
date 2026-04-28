# mobile2 patch set — applied snapshot

The 36 `.patch` files in this directory are the original `git format-patch` artifacts
from `https://github.com/trivartatech/mobile2`, applied to `main` on **2026-04-28**
across 8 sequential wave commits.

**The code changes are already in `main`.** This directory is committed for audit /
rollback / reference only. A fresh deploy does NOT need to re-apply these files —
they'll already be merged in whatever commit you `git clone`.

## Wave commits

| Wave | Patches    | Theme |
|:----:|------------|---|
| 1    | 0001-0005  | Mobile API + parent flow bug fixes |
| 2    | 0006-0012  | User-mgmt bulk actions + hotfixes |
| 3    | 0013-0015  | `useFormat()` composable + mobile `*_display` fields |
| 4    | 0016-0018  | Chat search + start-direct, parent class/section filter |
| 5    | 0019-0021  | Staff-attendance audit |
| 6    | 0022-0024  | Holiday autofill, leave G1/G6/G7, unmarked count |
| 7    | 0025-0027  | Staff + student QR badges (employee_id / admission_number based) |
| 8    | 0028-0036  | Fee voice reminder, Expo push, exam papers, driver, gate-keeper |

Look up the wave commits via:

```bash
git log --oneline --grep '^chore(mobile2): apply wave'
```

## Schema migrations introduced

| Migration | Wave | Purpose |
|---|:--:|---|
| `2026_04_27_114325_add_holiday_to_attendance_status_enum` | 5 | `'holiday'` value on `attendances.status` enum |
| `2026_04_27_150000_add_expo_push_token_to_users`          | 8 | `expo_push_token`, `device_platform`, `push_token_updated_at` on `users` |

## Manual conflict resolutions during apply

Recorded in the wave commit messages — these are merges with this school's
divergence, not part of the upstream patch:

| File | Wave | Kept |
|---|:--:|---|
| `app/Http/Controllers/School/UserManagementController.php` | 2 | Patch's classes/sections/missing_counts shape, `sort_order` |
| `resources/js/Pages/School/Students/Index.vue`            | 7 | Custom defaulter filter + new QR Badges PDF button |
| `resources/js/Pages/School/Finance/Ledger/DueReport.vue`  | 8 | Custom rich row layout + GL re-coupling + new Send Reminder buttons (fixed `student.` → `row.`) |

## Deploying to a fresh host

A new deployment of this school needs the standard Laravel post-clone sequence —
no patch application is required. See `POST_APPLY_RUNBOOK.md` for the live-server
upgrade procedure (also valid for fresh deploys, minus the `git pull`).

## Rolling back the whole set

Tag `pre-mobile2-patches` is anchored at the commit before Wave 1:

```bash
git reset --hard pre-mobile2-patches
php artisan migrate:rollback --step=2   # the 2 schema migrations from waves 5/8
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize:clear && php artisan config:cache && php artisan route:cache
```

Or restore from the SQL backup taken before deploy:

```bash
mysql -u "$DB_USER" -p "$DB_NAME" < ~/backup-pre-mobile2-<timestamp>.sql
```

## Sharing with sister deployments

Other school instances managed by Trivartha (basavaclik, basva, nescta, sahyadri,
sahyadricta) live in separate repos. To apply this same set there, either:

1. Pull the patches from `https://github.com/trivartatech/mobile2` and apply directly
   to that school's `main` (best — keeps each school's history clean), or
2. Copy this `deploy/mobile2/` directory into the sister repo and replay the same
   `git apply --3way` sequence wave by wave.
