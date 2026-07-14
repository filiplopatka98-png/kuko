# Vývoj → commit → deploy: ako to funguje

Tento dokument vysvetľuje, čo sa kde deje pri zmene kódu, od editácie na lokálnom Macu po nasadenie na WebSupport. Nie sú tu žiadne magické skripty — všetko je manuálne a transparentné.

## Aktuálny stack v kocke

```
┌──────────────────┐    git push      ┌────────────────┐
│   LOCAL (Mac)    │ ───────────────► │ GitHub (TODO)  │
│  ~/Downloads/    │                  │  origin        │
│  kuko-detskysvet │                  └────────────────┘
└────────┬─────────┘
         │ SFTP upload
         │ (cez lftp/Forklift/Cyberduck)
         ▼
┌──────────────────────────────────────────────┐
│ WebSupport — kukodetskysvet.sk              │
│  /web/           ← DocumentRoot (verejné)    │
│  /private/       ← lib, templates, migrations│
│  /config/        ← config.php (secrets)      │
│  /logs/          ← Apache error logs         │
└────────┬─────────────────────────────────────┘
         │ DB connection (TCP)
         ▼
┌──────────────────────────────────────────────┐
│ WebSupport MySQL — db.r6.websupport.sk:3306  │
│  DB: Y8U62i06                                │
│  User: Y8U62i06                              │
└──────────────────────────────────────────────┘
```

## 1. Lokálny vývoj

Pracuješ v `/Users/filiplopatka/Downloads/kuko-detskysvet`. Štruktúra:

| Adresár | Obsah |
|---|---|
| `public/`           | DocumentRoot — `.htaccess`, `index.php`, `admin/`, `api/`, `assets/` |
| `private/lib/`      | PHP triedy (Auth, Db, Availability, …) |
| `private/templates/`| PHP šablóny (sekcie, admin UI, mail) |
| `private/migrations/` | SQL migrácie (`001_init.sql`, `002_booking.sql`, …) |
| `private/scripts/`  | Pomocné CLI skripty (init dev DB, optimize images) |
| `private/tests/`    | PHPUnit testy |
| `config/`           | `config.example.php` (v gite), `config.php` (gitignored, dev hodnoty) |
| `docs/`             | Spec, plán, deploy návod, tento dokument |

### Spustenie dev servera

```bash
php -S 127.0.0.1:8000 -t public public/router.php
```

`public/router.php` emuluje `.htaccess` rewrites — produkčný Apache to robí natívne. Lokálny server používa SQLite (`private/logs/kuko-dev.sqlite`); produkcia MySQL.

### Spustenie testov

```bash
php private/lib/vendor/phpunit.phar
# alebo cez wrapper:
php private/tests/run.php
```

72 testov, beží do 0.1 sekundy. Pred každým commitom očakávame zelené.

### Reset dev DB

```bash
php private/scripts/dev-db-init.php
```

Zmaže `private/logs/kuko-dev.sqlite` a vytvorí nový so seed dátami (3 balíčky, 7 dní opening hours, default settings).

---

## 2. Commit

### Git config

Repo je lokálny git, **bez vzdialeného remote** (GitHub nie je pripojený). Všetky commity sú teda len na disku Macu. Pri zmene počítača treba buď:

- **A)** Pridať GitHub remote a pushnuť tam:
  ```bash
  gh repo create kuko-detskysvet --private --source=. --remote=origin --push
  # alebo manuálne: git remote add origin git@github.com:USERNAME/kuko-detskysvet.git && git push -u origin main
  ```
- **B)** Backup celého `.git/` adresára (rsync na externý disk, Time Machine, atď.)

Bez vzdialeného remote-u **strata Macu = strata histórie** (kód samotný je aj na WebSupport, ale bez commit log-u).

### Konvencia commit message

```
feat: pridanie X
fix(deploy): oprava Y
docs: aktualizácia Z
chore: housekeeping
```

Telo: 1-2 odseky o tom **prečo** (nie čo — to je v diff-e), prípadne odkaz na issue/decision.

Footer: `Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>` — pridáva sa pri commitoch, ktoré som spoluvytváral.

### Workflow

```bash
git status
git diff
git add -A      # alebo selectívne git add <files>
git commit -m "feat: ..."
```

**Čo ide do gitu:**
- ✅ Kód: `private/lib/`, `private/templates/`, `public/` (okrem secrets)
- ✅ Testy: `private/tests/`
- ✅ Migrácie: `private/migrations/*.sql`
- ✅ Docs: `docs/`
- ✅ Config template: `config/config.example.php`

**Čo NIKDY nejde do gitu** (z `.gitignore`):
- ❌ `config/config.php`            (db credentials, SMTP heslo, reCAPTCHA secret)
- ❌ `config/secrets.local.md`      (poznámka so secrets pre prod)
- ❌ `public/admin/.htpasswd`        (bcrypt hashe admin používateľov)
- ❌ `private/logs/*`                 (logy, dev SQLite DB)
- ❌ `private/lib/vendor/phpunit.phar` (binary, sťahovať podľa potreby)
- ❌ `.DS_Store`, `*.swp`, editor crud

Vždy pred commitom skontroluj `git status` že tieto súbory nie sú medzi staged-mi.

---

## 3. Deploy na WebSupport

WebSupport ponúka **SFTP-only účet** (žiadne SSH, žiadne shell príkazy). Migrácie a setup operácie sa preto robia cez admin stránku **`/admin/tools`** (za prihlásením; predtým verejný `public/_setup.php`, ktorý bol odstránený).

### Prerekvizity (raz)

- `brew install lftp sshpass` (pre non-interactive SFTP)
- SFTP credentials uložené v password manageri:
  ```
  host: kukodetskysvet.sk
  port: 22
  user: filip.kukodetskysvet.sk
  pass: <heslo>
  remote_path: kukodetskysvet.sk/web
  ```

### Adresárová štruktúra na serveri

```
/                                       (SFTP root)
├── kukodetskysvet.sk/
│   ├── logs/                           ← Apache error_log (read-only)
│   ├── sub/                            ← subdomény (nepoužívame)
│   ├── web/                            ← DocumentRoot
│   │   ├── .htaccess
│   │   ├── index.php                   ← front controller
│   │   ├── admin/                      ← admin app
│   │   ├── api/                        ← API endpoints
│   │   └── assets/                     ← static (CSS/JS/img/fonts)
│   ├── private/                        ← MIMO DocumentRoot, neviditeľné z webu
│   │   ├── lib/                        ← PHP triedy
│   │   ├── templates/                  ← PHP šablóny
│   │   ├── migrations/                 ← SQL migrácie
│   │   ├── scripts/                    ← deploy/maintenance skripty
│   │   ├── cron/                       ← (zatiaľ prázdne)
│   │   └── logs/                       ← app logs
│   └── config/
│       └── config.php                  ← secrets — NIKDY nie z gitu
```

`web/index.php` načítava `../private/lib/App.php` cez `dirname(__DIR__)`. PHP-FPM má pre tento účet povolený prístup k súborom v `/kukodetskysvet.sk/`, takže `private/` a `config/` sú síce nedostupné cez HTTP (mimo DocumentRoot), ale PHP ich vie zahrnúť.

### Upload (SFTP)

Pre routinnú zmenu (textová úprava, CSS, šablóna):

```bash
lftp -e "
  open -p 22 -u 'filip.kukodetskysvet.sk,<heslo>' sftp://kukodetskysvet.sk;
  put public/index.php -o kukodetskysvet.sk/web/index.php;
  put private/templates/sections/hero.php -o kukodetskysvet.sk/private/templates/sections/hero.php;
  bye
"
```

Pre väčšiu sériu zmien (po commitu):

```bash
lftp -e "
  open -p 22 -u 'filip.kukodetskysvet.sk,<heslo>' sftp://kukodetskysvet.sk;
  mirror -R --no-empty-dirs --only-newer --exclude=router.php public/ kukodetskysvet.sk/web/;
  mirror -R --no-empty-dirs --only-newer --exclude=tests/ --exclude=logs/kuko-dev.sqlite --exclude=lib/vendor/ private/ kukodetskysvet.sk/private/;
  bye
"
```

`mirror -R --only-newer` nahrá iba zmenené súbory (porovnáva timestamps). `--exclude` chráni dev súbory (testy, SQLite DB, PHPUnit phar).

**GUI alternatívy:**
- **Forklift** (macOS, plat.) — drag-drop, profil so credentials
- **Cyberduck** (zdarma) — pohodlnejší než CLI pre veľké uploady
- **Termius** — má SFTP klient s diff view

### Configuračné secrets

`config/config.php` na **serveri** obsahuje produkčné hodnoty (DB creds, SMTP heslo, reCAPTCHA secret, hashes). Tieto sa **nikdy nemiešajú** s lokálnym dev config-om.

Pri zmene config-u na produkcii:

1. Stiahni si aktuálny: `lftp ... get kukodetskysvet.sk/config/config.php -o /tmp/prod-config.php`
2. Edit lokálne (v `/tmp/`, nie v repo)
3. Upload späť: `put /tmp/prod-config.php -o kukodetskysvet.sk/config/config.php`

**Nikdy** neuploaduj `config.php` z repo — by si prepísal prod secrets svojou dev SQLite konfiguráciou.

### Migrácie

Server je SFTP-only, takže `php private/migrations/run.php` priamo na serveri spustiť nedá. Riešenie: **admin stránka `/admin/tools`** (za prihlásením) — `public/_setup.php` bol odstránený (žiadny verejný endpoint ani token v URL). Admin login je súborový (`config/.htpasswd`), takže funguje aj pred migráciou DB.

Kroky (po nahratí zmeneného kódu cez SFTP):

1. Prihlás sa do `/admin`, otvor **Web & systém → Nástroje**.
2. **Spustiť migrácie** — aplikuje nové `*.sql` (idempotentné, výstup `+ apply … / all migrations applied`).
3. **Spustiť seed** — doplní chýbajúce content bloky/nastavenia (insert-only).
4. **Smoke test** — overí DB a vypíše tabuľky (bez zmien).

Logika je v `\Kuko\DeployTools` (testovateľná); routy `GET /admin/tools` + `POST /admin/tools/run` (CSRF) v `public/admin/index.php`.

### Admin používatelia (htpasswd)

`config/.htpasswd` obsahuje bcrypt hashe (jeden používateľ na riadok). Auth ho
číta z `config/.htpasswd` — **mimo** webroot-u (`web/`), takže súbor nie je
verejne prístupný. Pridanie / reset admina (aj recovery ak si zabudol heslo):

1. Lokálne nastav / resetni heslo (interaktívne, súbor je gitignored):

```bash
/opt/homebrew/bin/php private/scripts/admin-passwd.php
```

2. Nahraj výsledný súbor na produkciu (jedným lftp put):

```bash
lftp -e "
  open -p 22 -u 'filip.kukodetskysvet.sk,<heslo>' sftp://kukodetskysvet.sk;
  put config/.htpasswd -o kukodetskysvet.sk/config/.htpasswd;
  bye
"
```

3. Hotovo — Auth číta `config/.htpasswd` (mimo webroot-u), zmena platí ihneď.

### Verifikácia po deploy

```bash
# Public page (po vypnutí maintenance):
curl -sw "%{http_code}\n" -o /dev/null https://kukodetskysvet.sk/

# Maintenance — bez cookie 503:
curl -sw "%{http_code}\n" -o /dev/null https://kukodetskysvet.sk/

# Admin login:
curl -sw "%{http_code}\n" -o /dev/null https://kukodetskysvet.sk/admin/login

# API:
curl "https://kukodetskysvet.sk/api/availability?date=2026-06-15&package=mini"
```

### Server logy

Apache `error_log` je v `kukodetskysvet.sk/logs/`. Stiahnutie:

```bash
lftp ... get kukodetskysvet.sk/logs/error_log -o /tmp/kuko-error.log
tail -50 /tmp/kuko-error.log
```

PHP fatals + warnings sa logujú sem. WebSupport rotuje denne (`error_log-YYYY-MM-DD.gz` archív).

---

## 4. Databáza

### Production DB

| Vec | Hodnota |
|---|---|
| Server | `db.r6.websupport.sk:3306` |
| DB name | `Y8U62i06` |
| User | `Y8U62i06` |
| Password | uložené v `config/config.php` (na serveri) |
| Charset | `utf8mb4_unicode_ci` (po migrácii 004) |

### Pripojenie z lokálu (read-only debug)

WebSupport DB má firewall — povoľuje pripojenie **len z infrastruktury WebSupport** alebo z whitelisted IP. Lokálne pripojenie typicky nefunguje out-of-the-box.

Workaround: WebSupport admin panel ponúka phpMyAdmin pre web-based DB management. Alebo SSH tunel (ak by si dostal SSH).

### Backup

WebSupport robí denné automatické zálohy DB (uchované 7 dní). Pre vlastnú istotu:

```bash
# Cez phpMyAdmin export → SQL → stiahni .sql.gz
# alebo cez WebSupport zákazníckeho panela: Database → Export
```

Cron script pre týždenný dump cez SFTP TODO (zatiaľ ručne mesačne).

### Migrácie - workflow

1. **Lokálne**: vytvor nový `private/migrations/NNN_popis.sql`
2. **Test lokálne** cez SQLite (pre developer convenience). Pozn.: niektoré MySQL features ako `ENUM`, `JSON`, `ON UPDATE CURRENT_TIMESTAMP` SQLite nepodporuje — preto v dev DB seed sa nepoužíva runner, ale `dev-db-init.php` script.
3. **Commit** súboru.
4. **Upload** súboru cez SFTP do `kukodetskysvet.sk/private/migrations/`.
5. **Spusti** cez `/admin/tools` → *Spustiť migrácie* (za prihlásením).
6. **Overuj** že `migrations` tabuľka má nový riadok (*Smoke test*).

Migrácie sú idempotentné cez `CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`, prípadne explicit checks. Spustenie 2× neuškodí — runner sám skipuje aplikované migrácie.

---

## 5. Procedúra release-u (od commitu po prod)

Pre štandardný release (kód + DB zmena):

1. **Lokálne**
   - Edit kód
   - Pridaj testy ak treba
   - `php private/lib/vendor/phpunit.phar` → zelené
   - `find private/lib private/templates public -name "*.php" -exec php -l {} \;` → bez chýb
   - `git add -A && git commit -m "..."`
2. **Manual UAT lokálne**
   - `php -S 127.0.0.1:8000 -t public public/router.php`
   - Prejdi flow ktorý si menil
3. **Upload**
   - `lftp ... mirror -R --only-newer public/ ...`
   - `lftp ... mirror -R --only-newer private/ ...`
4. **Migrácie (ak treba)**
   - Upload nový `.sql` cez SFTP
   - `/admin/tools` → *Spustiť migrácie*
5. **Smoke test prod**
   - `curl -I https://kukodetskysvet.sk/`
   - Otvor v prehliadači, prejdi flow
6. **Sledovať error log**
   - `lftp ... get kukodetskysvet.sk/logs/error_log -o /tmp/x.log && tail -30 /tmp/x.log`

Pre release **len lokalizačné zmeny** (text, ALT, malá CSS úprava):

1. Edit
2. `git commit`
3. `lftp ... put <súbor>`
4. Smoke

Pre release **len obsahu cez admin CMS** (po dokončení CMS):

- Žiadny SFTP, žiadny deploy. Vlastník edituje cez `/admin/content`.

---

## 6. Rollback

Ak po deploy niečo zlyhá:

### Kód

```bash
git log --oneline | head
# nájdi posledný funkčný commit, napr. 515fa92
git checkout 515fa92 -- <súbor>     # alebo celý tree
# pochopiteľne re-upload cez SFTP
```

Alternatívne: stiahni si snapshot pred deploy-om (`lftp mirror` opačným smerom do `/tmp/kuko-backup/`), v prípade chyby ho upload-uj späť.

### DB

WebSupport má denné automatické zálohy. Cez ich panel: `Database → Backups → Restore`. Pri rollback-u stratíš zmeny po zálohe — odporúčam **pred každou migráciou robiť ručný dump** cez phpMyAdmin.

---

## 7. TODO pre tento workflow

Veci, čo by vylepšili proces ale nie sú kritické:

- **GitHub remote** — pre history backup + možno deploy hooky
- **CI/CD** (GitHub Actions) — automaticky spustiť testy + lint pri push, voliteľne deploy do staging
- **Staging subdoména** — `staging.kukodetskysvet.sk` pre testovanie pred prod
- **Auto-backup cron** — týždenný dump DB cez SFTP do lokálu
- **Deploy bash skript** — `./private/scripts/deploy.sh` ktorý spojí lftp + migrate + smoke do jedného príkazu
- **Verzia v UI** — footer zobrazí `git rev-parse --short HEAD` (zapečené pri deploy) pre debug
