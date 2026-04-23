# Deploy — National English School, Chitradurga

School: **National English School**
Address: Horpete, Vijayanagar, Chitradurga, Karnataka 577502
Target domain: **nescta.in** (update `APP_URL` in `.env` if different)
Repo: **https://github.com/trivartatech/nescta**
DB: **MySQL** (database created during deploy)

---

## 0. Prereqs on the server

- PHP 8.2+ (with `pdo_mysql`, `mbstring`, `bcmath`, `gd`, `zip`, `xml`, `curl`, `intl`)
- Composer 2.x
- Node 20+ and npm (only needed if frontend is not pre-built)
- MySQL 8.x running and reachable
- Web server (Nginx/Apache) pointing the domain at `public/`
- SSL

---

## 1. First-time deploy

```bash
# On the server
cd /home/<your-user>/htdocs/nescta.in   # CloudPanel path
# or wherever the vhost root is

git clone https://github.com/trivartatech/nescta.git .

cp .env.nes-production.example .env

# Edit .env and fill in:
#   APP_URL=https://nescta.in          (or your actual domain)
#   DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD
#   DB_ROOT_USERNAME / DB_ROOT_PASSWORD   (optional — only if you want the script to create the DB + user)
nano .env

chmod +x deploy-nes.sh
./deploy-nes.sh
```

That's it. The script will:
1. Create the MySQL database + user (if root creds provided)
2. `composer install --no-dev`
3. Generate `APP_KEY`
4. `storage:link`
5. Run migrations
6. Seed roles, the school, grading system, communication templates
7. Build frontend assets (if `public/build` doesn't exist)
8. Cache config/routes/views/events

---

## 2. Default logins

Change every password after first login.

| Role | Email | Temp password |
|---|---|---|
| Super admin | `superadmin@nescta.in` | `ChangeMe@2026` |
| School admin | `admin@nescta.in` | `ChangeMe@2026` |
| Principal | `principal@nescta.in` | `ChangeMe@2026` |

Login URL: `https://nescta.in/login`

---

## 3. Post-deploy (one-time)

### Cron (Laravel scheduler)

```bash
crontab -e
```

```
* * * * * cd /home/<your-user>/htdocs/nescta.in && php artisan schedule:run >> /dev/null 2>&1
```

### Queue worker (required for notifications, SMS, email, broadcasts)

Supervisor config (`/etc/supervisor/conf.d/nes-queue.conf`):

```ini
[program:nes-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /home/<your-user>/htdocs/nescta.in/artisan queue:work --queue=default,notifications --tries=1 --sleep=3 --max-time=3600
autostart=true
autorestart=true
user=<your-user>
numprocs=1
redirect_stderr=true
stdout_logfile=/home/<your-user>/htdocs/nescta.in/storage/logs/queue.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start nes-queue:*
```

### Web server document root

Make sure Nginx/Apache points to `/path/to/app/public/` — **not** the repo root.

---

## 4. Subsequent updates

Use the generic `deploy.sh`:

```bash
cd /home/<your-user>/htdocs/nescta.in
git pull origin main
./deploy.sh
```

---

## 5. Data loaded on fresh deploy

This is a **clean** install — no demo students, staff, or fees. Only:

- Roles & permissions (Spatie)
- The school + trust/organization record (National English School)
- Current academic year (April – March)
- Three admin users (above)
- Grading system defaults
- Communication message templates

All student/staff/fee/exam data is entered via the UI afterwards.

---

## 6. School profile

The seeder pre-fills:

- **Name:** National English School
- **Code:** NES001
- **Board:** State (change in UI → Settings → School if CBSE/ICSE)
- **Address:** Horpete, Vijayanagar
- **City/State/PIN:** Chitradurga, Karnataka, 577502
- **Timezone:** Asia/Kolkata
- **Currency:** INR

Update phone number, principal name, and logo from the school settings UI after first login.

---

## 7. Rollback

If the first deploy fails midway:

```bash
mysql -u root -p -e "DROP DATABASE nes_erp;"
rm -rf vendor node_modules public/build
# fix the issue, then re-run ./deploy-nes.sh
```
