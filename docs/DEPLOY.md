# Deployment — KUKO detský svet

Cieľová platforma: **WebSupport** (Apache 2 + PHP 8.1+ + MySQL/MariaDB).

## 1. Pre-deploy checklist

| Položka | Akcia |
|---|---|
| **Doména** | `kuko-detskysvet.sk` smeruje na WebSupport nameservery |
| **SSL** | Aktivovať Let's Encrypt vo WebSupport admine |
| **Databáza** | Vytvoriť MySQL/MariaDB DB s collation `utf8mb4_unicode_ci`. Poznamenať host, name, user, password |
| **Mailbox** | Vytvoriť mailbox `info@kuko-detskysvet.sk`, poznamenať SMTP heslo |
| **reCAPTCHA v3** | Na https://www.google.com/recaptcha/admin/create vytvoriť kľúč pre `kuko-detskysvet.sk`. Poznamenať `site_key` a `secret_key` |
| **Sociálne siete** | URL Facebook a Instagram (zadávajú sa do `config.php → social.*`) |

## 2. Adresárová štruktúra na serveri

Ideálny rozdeľ medzi `public/` a `private/`:

```
~/web/                   # DocumentRoot
  index.php
  .htaccess
  robots.txt
  sitemap.xml
  admin/  api/  assets/
~/private/               # mimo DocumentRoot, neprístupné z internetu
  lib/  templates/  migrations/  logs/  cron/  scripts/
~/config/
  config.php
```

Ak WebSupport nepovolí cesty mimo webrootu, ponechaj `private/` a `config/` vnútri repo (zachová si chránenosť cez `private/.htaccess` blokujúce HTTP prístup — pridaj `Require all denied` pred uploadom).

## 3. Konfigurácia

1. SSH/SFTP do WebSupportu.
2. Skopíruj `config/config.example.php` ako `config/config.php`.
3. Vyplň:
   - `db.host`, `db.name`, `db.user`, `db.pass`
   - `mail.host=smtp.websupport.sk`, `mail.port=465`, `mail.encryption=ssl`, `mail.user`, `mail.pass`
   - `mail.from_email=info@kuko-detskysvet.sk`, `mail.admin_to=info@kuko-detskysvet.sk`
   - `recaptcha.site_key`, `recaptcha.secret_key` (z `config/secrets.local.md` — gitignored)
   - `recaptcha.min_score` = `0.5`
   - `security.ip_hash_secret` = `openssl rand -hex 32`
   - `auth.secret` = `openssl rand -hex 32`
   - `social.facebook`, `social.instagram`
   - `app.env=production`, `app.debug=false`

## 4. Migrácie

Cez SSH:

```bash
cd ~/private  # alebo do repo root
php migrations/run.php
```

Výstup:
```
+ apply 001_init.sql
  done
all migrations applied
```

## 5. Admin Basic Auth

```bash
htpasswd -nbB admin '<silne-heslo>' > public/admin/.htpasswd
chmod 600 public/admin/.htpasswd
```

V `public/admin/.htaccess` odkomentuj a uprav `AuthUserFile` na absolútnu cestu:

```apache
AuthUserFile /full/absolute/path/to/public/admin/.htpasswd
```

Skontroluj prístup: `https://kuko-detskysvet.sk/admin/` musí pýtať heslo.

## 6. Force HTTPS

V `public/.htaccess` odkomentuj:

```apache
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

## 7. WebP konverzia

Pred prvým deploy-om lokálne:

```bash
brew install webp
./private/scripts/optimize-images.sh
```

`*.webp` súbory sú v `public/assets/img/` — nahraj ich spolu s ostatným.

## 8. Smoke test po deploy-i

- `https://kuko-detskysvet.sk/` → homepage so všetkými sekciami, fonty + obrázky.
- `https://kuko-detskysvet.sk/ochrana-udajov` → privacy page.
- `https://kuko-detskysvet.sk/neexistuje` → 404 page.
- Cookie banner → klik „Súhlasím" → zmizne.
- Modal: klik „Rezervovať balíček" → otvorí sa, súhlas s cookies → submit → e-mail dorazí, autoreply tiež.
- Admin: `https://kuko-detskysvet.sk/admin/` → Basic Auth dialog → po prihlásení vidieť rezerváciu → status change → audit log v `admin_actions`.

## 9. Lokálny dev

```bash
brew install php
php -S 127.0.0.1:8000 -t public public/router.php
open http://127.0.0.1:8000/
```

`router.php` emuluje `.htaccess` rewrites pre PHP built-in server. Pre lokálne testy admin panelu treba MySQL alebo upraviť `config.php` na SQLite (pozri komentár v `config/config.example.php`).

## 10. Backup

WebSupport robí denné DB zálohy. Pre extra istotu:
- Týždenne ručne stiahnuť `mysqldump` cez SSH.
- Logy v `private/logs/` retencia 6 mesiacov.
