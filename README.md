# KUKO detský svet

Web pre **KUKO detský svet** — interiérové detské ihrisko + kaviareň v Piešťanoch
(Bratislavská 141, 921 01 Piešťany · +421 915 319 934 · info@kuko-detskysvet.sk ·
Po–Ne 9:00–20:00). Jednostránkový prezentačný web + viackrokový rezervačný systém
osláv + WordPress-style admin.

> **Stav:** pred-launch. Produkcia je za maintenance bránou (`/` → 503) a
> `robots.txt` má `Disallow: /`. Go-live = vypnúť maintenance + zapnúť indexáciu
> (owner kroky, viď `docs/`).

## Tech stack

- **PHP 8.1**, bez frameworku — vlastný router + jedna trieda na súbor v `Kuko\` namespace
- **MySQL/MariaDB** na produkcii, **SQLite** pre lokálny dev
- **PHPUnit 10.5** (phar v `private/lib/vendor/phpunit.phar`)
- Vanilla JS (ES moduly), CSS s `:root` tokenmi; `esbuild` len na minifikáciu
- Hosting: **WebSupport** (Apache + PHP-FPM, SFTP-only — žiadny SSH)

## Štruktúra

```
public/            # DocumentRoot (na serveri → web/)
  index.php          front controller / router
  admin/ api/        admin app, JSON API endpointy
  assets/            css js img icons fonts (+ committed *.min.*)
private/             mimo DocumentRoot
  lib/               PHP triedy (Db, Availability, Reservation, Content, …)
  templates/         PHP šablóny (pages/, sections/, layout*.php, admin/)
  migrations/        SQL migrácie (idempotentné)
  scripts/           seed-cms.php, build-assets.php, dev-db-init.php
  cron/              retention.php, db-backup.php, expire-pending.php
  tests/             unit/ + integration/
config/              config.php (NIKDY v gite), .htpasswd, secrets.local.md
docs/                DEPLOY.md, WORKFLOW.md, RECOVERY.md, plans/
```

## Lokálny vývoj

```bash
php private/scripts/dev-db-init.php          # vytvorí SQLite dev DB + schému
php private/scripts/seed-cms.php             # naseeduje obsah/balíčky/galériu
php -S 127.0.0.1:8123 -t public public/router.php
open http://127.0.0.1:8123/
```

`config/config.php` pre dev používa SQLite a `app.maintenance=false`
(viď `config/config.example.php`).

## Testy / build

```bash
php private/lib/vendor/phpunit.phar -c phpunit.xml      # celá suita
php private/scripts/build-assets.php                    # regeneruj *.min.*
```

Po každej zmene CSS/JS zdroja **znova spusti `build-assets.php`** a commitni
regenerované `*.min.*` (stale-min guard test to vynucuje). `Kuko\Asset::url()`
cache-bustuje (`?v=<mtime>`) a preferuje `.min` súrodenca.

## Deploy

SFTP-only — pozri **`docs/DEPLOY.md`** (vrátane povinnej sekcie *Cron úlohy*).
V skratke: `git push` → `lftp` mirror len zmenených súborov `public/`→`web/`,
`private/`→`private/` → token-gated `_setup.php?action=migrate|seed` → overiť
invarianty (public `/`=503, `robots Disallow:/`, `/admin/login`=200).
`config/config.php` na serveri sa **nikdy** neprepisuje z gitu.

## Ďalšia dokumentácia

- `docs/DEPLOY.md` — produkčné nasadenie + cron registrácia
- `docs/WORKFLOW.md` — vývoj → commit → deploy tok
- `docs/RECOVERY.md` — obnova DB/webu
- `docs/plans/` — špecifikácie a priebežný STATUS/handoff
- `CLAUDE.md` — konvencie a pracovný postup pre AI asistenta
