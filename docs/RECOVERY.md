# Disaster recovery runbook — KUKO detský svet

Tight, ordered steps for "the site / DB is down". Deploy mechanics are NOT
repeated here — see `docs/WORKFLOW.md` (§3 Deploy, §"Admin používatelia")
and `docs/DEPLOY.md`. This document only covers *restoring* a broken site.

---

## 1. Symptoms / triage

| Symptom | Likely cause |
|---|---|
| White screen / HTTP 500 on every page | bad deploy, missing `config/config.php`, fatal PHP error |
| Public pages OK but DB-backed pages error (reservations, CMS) | DB down / wrong `db.*` creds / dropped tables |
| HTTP 503 / "we're updating" branded page | `app.maintenance = true` in config (intended), not an outage |
| `/admin/login` shows 500 or "cannot read .htpasswd" | `config/.htpasswd` missing or wrong perms |

Quick checks:

```bash
curl -sI https://kuko-detskysvet.sk/            # expect 200 (or 503 if maintenance)
curl -s  https://kuko-detskysvet.sk/robots.txt  # expect a body
curl -sI https://kuko-detskysvet.sk/admin/login # expect 200
```

---

## 2. Restore the database

Pick the freshest good source. Preference: WebSupport daily backup first,
then our app-owned dump.

**Option A — WebSupport daily backup (operator panel).** Restore the MySQL
database `kuko` from the WebSupport hosting backup UI. This is the primary
source and is taken automatically every day.

**Option B — our app dump (`private/logs/backups/kuko-*.sql.gz`).** Produced
weekly by `private/cron/db-backup.php`. Use the newest (or a known-good older)
archive. Get it onto a machine with `mysql` client access:

```bash
gunzip -k kuko-YYYYmmdd-HHMMSS.sql.gz
mysql --host=<db-host> --user=<db-user> -p kuko < kuko-YYYYmmdd-HHMMSS.sql
# (password is the prod db.pass from the password manager / config.php)
```

**Dev (SQLite):** there is no MySQL — just restore the file. Stop using the
app, then replace `private/logs/kuko-dev.sqlite` with the desired
`private/logs/backups/kuko-YYYYmmdd-HHMMSS.sqlite` (plain `cp`). If no backup
exists, re-init: `php private/scripts/dev-db-init.php`.

---

## 3. Redeploy the code

If the outage is a bad deploy, push a known-good `main` back up using the
documented lftp mirror — see `docs/WORKFLOW.md` §3 ("Deploy na WebSupport",
the `lftp -e "... mirror -R --only-newer ..."` block). Mirroring is
idempotent; re-running it with a clean checkout overwrites the broken files.

---

## 4. Restore `config/config.php`

`config/config.php` is **never in git** (it holds DB pass, mail pass,
`auth.secret`, reCAPTCHA keys). Recreate it on the server from the password
manager:

1. Copy `config/config.example.php` → `config/config.php`.
2. Fill every secret from the password manager (db user/pass, `auth.secret`,
   `mail.pass`, reCAPTCHA keys). `auth.secret` MUST match the previous value
   or all remember-me cookies and CSRF tokens are invalidated.
3. lftp `put config/config.php -o kuko-detskysvet.sk/config/config.php`.

---

## 5. Recreate `config/.htpasswd` (admin login)

Also never in git. Regenerate and upload (see `docs/WORKFLOW.md`
§"Admin používatelia"):

```bash
/opt/homebrew/bin/php private/scripts/admin-passwd.php   # writes config/.htpasswd
lftp -e "put config/.htpasswd -o kuko-detskysvet.sk/config/.htpasswd; bye" ...
```

`config/.htpasswd` lives outside the webroot; the change is effective
immediately (no restart).

---

## 6. Post-recovery smoke test

```bash
curl -sI https://kuko-detskysvet.sk/             # 200 (or 503 if maintenance still on)
curl -s  https://kuko-detskysvet.sk/robots.txt   # body present
curl -sI https://kuko-detskysvet.sk/admin/login  # 200, then log in with the new htpasswd creds

# Re-apply DB migrations (server is SFTP-only — use the gated PHP helper):
TOKEN=<auth.secret from config.php>
curl "https://kuko-detskysvet.sk/_setup.php?action=migrate&token=$TOKEN"
# expect: "... all migrations applied"
curl "https://kuko-detskysvet.sk/_setup.php?action=smoke&token=$TOKEN"
```

Then exercise a real reservation submit and confirm the admin list shows it.
If `app.maintenance` was flipped on during recovery, set it back to `false`
in `config/config.php` and re-upload. Finally, delete `_setup.php` from the
server (`?action=delete&token=$TOKEN`).
