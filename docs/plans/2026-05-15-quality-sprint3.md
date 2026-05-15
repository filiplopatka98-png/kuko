# Quality Sprint 3 — a11y + UX + security hardening

> Execute via subagent-driven-development (implementer → spec review → code-quality review per task), deploy after the sprint. Spec = `docs/plans/2026-05-14-roadmap-quality.md` (A2/A3/A5/A6, U1/U3/U4, B5/B6). B3 (dependency audit) + B7 (ZAP/DNS/pentest) are owner/manual — reported, not built.

**Audited current state (do NOT redo / build on these facts):**
- NO skip link anywhere. `<main>` exists only in `admin/layout.php:37`; site `layout.php` & `layout-minimal.php` are just `<body><?= $content ?></body>` (header/nav/footer live inside `$content` at page/section level).
- `main.css`: `--c-text-soft: #7A7A7A` (≈4.0:1 on `#FFF8EE` — below WCAG AA 4.5:1). `prefers-reduced-motion` blocks exist (lines ~52,128). `:focus-visible` exists ONLY for `.calendar__grid [role=gridcell]` (line ~116) — no site-wide focus ring.
- `reservation.php`: inputs `f-name/f-phone/f-email` have `required` but no `aria-required`; error `<p class="step__error" id="form-error" hidden>` (line 132); success `<p id="success-link">` (line 147) inside `<section class="step" data-step="success">` (line 142, `<h2 class="step__title">Ďakujeme!</h2>`).
- `Availability::forDate()` (line ~107): `$rEnd = $rStart + duration + buffer` — buffer applied only AFTER a reservation (ASYMMETRIC). `subtract($intervals,$start,$end)` helper at line ~184. `buffer_min` setting default 30.
- Phone validation: `Reservation.php:45` loose `/^\+?[0-9 ()\/-]{7,20}$/`.
- `App.php::bootstrap()` sets `ini_set('display_errors', …)` by debug flag; NO session cookie hardening anywhere; session started lazily by `Csrf`/`Auth`.
- Tech: PHP 8.1 `/opt/homebrew/bin/php`; PHPUnit phar; node v20. `Kuko\Asset::url()` cache-busts + prefers `.min`; after editing any CSS/JS source RE-RUN `/opt/homebrew/bin/php private/scripts/build-assets.php` and commit regenerated `.min.*` (stale-min guard test enforces this). Deploy = lftp mirror. Currently 161 tests green.

---

### S3-T1 — A5 colour/contrast + site-wide focus-visible
**Files:** `public/assets/css/main.css`; regenerate `main.min.css`; test `private/tests/unit/ContrastFocusTest.php`.
- Change `--c-text-soft` from `#7A7A7A` to a value with ≥4.5:1 on `#FFF8EE` — use `#6A6A6A` (verify ratio ≥4.5; if not, `#636363`). Do NOT change `--c-text` (already AA).
- Add a site-wide keyboard focus ring (does not affect mouse): `:where(a, button, input, select, textarea, summary, [tabindex]):focus-visible { outline: 3px solid var(--c-accent,#D88BBE); outline-offset: 2px; border-radius: 4px; }` placed near existing focus styles; keep the existing calendar gridcell rule.
- Confirm an existing `@media (prefers-reduced-motion: reduce)` block neutralises the reservation step transition / scroll-reveal animations; if the step `fadeIn`/transition isn't covered, add it to a reduced-motion block.
- Test: assert main.css contains `#6A6A6A` (or chosen), NOT `#7A7A7A`; contains a `:focus-visible` rule that is NOT calendar-scoped (e.g. matches `:focus-visible` outside `.calendar__grid`); reduced-motion block present.
- Rebuild `main.min.css`; full suite green (incl. stale-min guard). Commit `feat: AA sub-text contrast + site-wide focus-visible (roadmap A5)`.

### S3-T2 — A2 skip link + `<main>` landmark
**Files:** `private/templates/layout.php`, `private/templates/layout-minimal.php`, `private/templates/admin/layout.php`, `public/assets/css/main.css` (+ rebuild min), `public/assets/css/admin.css` (+ none — admin.css not minified separately? it IS in build list — rebuild admin.min.css), test.
- Read each page template (home.php / faq.php / privacy.php etc.) to see how header/nav/footer are emitted inside `$content`. Add a visually-hidden-until-focus skip link as the FIRST body child and an `id="main"` landmark on the primary content region. Pragmatic, non-breaking approach: in `layout.php`/`layout-minimal.php` insert `<a class="skip-link" href="#main">Preskočiť na obsah</a>` immediately after `<body>`, and wrap `<?= $content ?>` so the main content is reachable: add `<div id="main" tabindex="-1">` … but only if it does NOT double-wrap existing `<main>`/sectioning. SAFEST: emit skip link after `<body>`, and add `id="main"` to the existing first content landmark. If pages have no `<main>`, wrap `<?= $content ?>` in `<main id="main" tabindex="-1">…</main>` in layout.php/layout-minimal.php (header/nav/footer are inside $content → acceptable: a single `<main>` around page content is still valid since there's no separate site chrome in the layout; verify the page templates don't already emit their own `<main>` to avoid nested main — if they do, just add `id="main"` there instead). admin/layout.php already has `<main class="admin-main">`: add `id="main"` to it + the skip link after its `<body>`.
- CSS `.skip-link`: visually hidden, becomes visible on `:focus` (position fixed top-left, high z-index, accent bg, padding). Add to `main.css` (site) and `admin.css` (admin). Rebuild both `.min`.
- Test: each of the 3 layouts contains `class="skip-link"` + `href="#main"` and an `id="main"`; `.skip-link` rule exists in main.css & admin.css.
- Full suite green. Commit `feat: skip link + main landmark (roadmap A2)`.

### S3-T3 — A3 form accessibility (reservation + admin)
**Files:** `private/templates/pages/reservation.php`, `public/assets/js/rezervacia.js` (+ rebuild rezervacia.min.js), maybe admin form partials; test.
- reservation.php: add `aria-required="true"` to required inputs (name/phone/email and any required step-1/step-2 controls) + a visual required marker (`<span class="req" aria-hidden="true">*</span>` in the label, with a one-line legend "* povinné"). Error container `#form-error`: add `role="alert"` + `aria-live="assertive"`. Success container (`data-step="success"` region / `#success-link`): add `role="status"` + `aria-live="polite"`. Associate field-level errors if any via `aria-describedby` (only where an error element exists; do not invent new error UI beyond the existing `#form-error`).
- rezervacia.js: when showing `#form-error`, ensure it's not `hidden` so the alert is announced; when entering success step, ensure the status region content is set so SR announces it. Keep existing behavior; only add ARIA/announce.
- Admin: light pass — ensure destructive admin forms already have `onsubmit confirm` (audit; A6 overlap) — no change unless a destructive action lacks confirm; report findings.
- Test (string-level on templates/JS): reservation.php has `aria-required="true"`, `role="alert"`, `role="status"`; rezervacia.js references the error/success elements with the announce.
- Rebuild rezervacia.min.js. Full suite green. Commit `feat: reservation form a11y — aria-required/alert/status (roadmap A3)`.

### S3-T4 — U1 symmetric time-slot buffer
**Files:** `private/lib/Availability.php`, `private/tests/...AvailabilityTest...` (find existing), maybe a settings doc.
- In `forDate()` reservation loop (~line 105-108): currently `$intervals = $this->subtract($intervals, $rStart, $rEnd)` with `$rEnd = $rStart + duration + buffer`. Make the buffer SYMMETRIC: subtract `[$rStart - $buffer, $rEnd]` where `$rEnd = $rStart + duration + buffer`. i.e. block buffer BEFORE and AFTER an existing reservation. Clamp lower bound at 0 (don't pass negative start; `subtract` should handle, but pass `max(0, $rStart - $buffer)`).
- Find the existing Availability test (grep `testExistingReservationBlocksWithBuffer` / AvailabilityTest). Update/extend it: an existing reservation must now also block a slot that would END within `buffer` before it starts (the case the roadmap calls out: MINI 12:00–14:00 before MAXI 14:00 must now be rejected when buffer 30). Add an explicit test for the pre-buffer.
- Full suite green. Commit `feat: symmetric time-slot buffer (roadmap U1)`.

### S3-T5 — U3 form quality-of-life
**Files:** `private/lib/Reservation.php` (server phone validation), `private/templates/pages/reservation.php` (datalist, default), `public/assets/js/rezervacia.js` (sessionStorage persist, default time) (+ rebuild min); tests.
- Server: tighten phone validation in `Reservation.php:45`. SK numbers: accept `+421` followed by 9 digits, or `0` followed by 9 digits, allowing spaces/`/`/`-`/`()` as separators. Regex e.g. strip separators then `^(\+421|0)[0-9]{9}$`. Keep the error message Slovak; add/extend a unit test for valid/invalid SK numbers (`+421 915 319 934`, `0915319934` valid; `12345` invalid).
- Template: email field — add `<input list="email-domains">` + `<datalist id="email-domains">` with common domains (gmail.com, azet.sk, zoznam.sk, centrum.sk, outlook.com, icloud.com) — keep `type="email"`/required/autocomplete. Smart default: prefill the time control to `14:00` if none chosen (do in JS, not hardcoded markup, so it doesn't fight the picker).
- rezervacia.js: persist in-progress form fields to `sessionStorage` on input and restore on load (clear on successful submit); set default time 14:00 when reaching step where time is chosen and nothing selected. Keep all existing wizard/calendar behavior.
- Tests: Reservation phone unit test (SK valid/invalid); string-level reservation.php has `<datalist`; rezervacia.js references `sessionStorage`.
- Rebuild rezervacia.min.js. Full suite green. Commit `feat: SK phone validation + email datalist + persist + default time (roadmap U3)`.

### S3-T6 — U4 add-to-calendar on success
**Files:** `private/templates/pages/reservation.php` (success step), possibly a tiny endpoint or client-side .ics blob; `public/assets/js/rezervacia.js` (+ rebuild min); test.
- On the success step, add an ".ics" download + a Google Calendar link for the booked party (title "Oslava v KUKO", location "Bratislavská 141, 921 01 Piešťany", start = wished_date+wished_time, duration = selected package duration). Simplest robust: build the `.ics` content client-side as a Blob from the data the success step already has (selected package/date/time), and a Google Calendar URL `https://calendar.google.com/calendar/render?action=TEMPLATE&text=…&dates=…&location=…`. Render into the existing success area (near `#success-link`).
- Keep it minimal and dependency-free. If the success step lacks the needed data attributes, add small `data-*` carriers populated by the existing flow (don't refactor the flow).
- Test: reservation.php / rezervacia.js contain the calendar link builder (string-level: `calendar.google.com/calendar/render`, `BEGIN:VCALENDAR`).
- Rebuild rezervacia.min.js. Full suite green. Commit `feat: add-to-calendar (.ics + Google) on reservation success (roadmap U4)`.

### S3-T7 — B6 hardening + B5 backup
**Files:** `private/lib/App.php` (session cookie params), `public/.htaccess` (disabled funcs — best-effort), `private/cron/db-backup.php` (new), `docs/RECOVERY.md` (new), test.
- App.php: BEFORE any session starts (central), set secure session cookie params. Add in `bootstrap()` (after config load, before app logic), guarded so it doesn't fight CLI/tests: if not CLI and session not yet active, `session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax'])` and `ini_set('session.use_strict_mode','1')`, `ini_set('session.cookie_secure','1')`, `ini_set('session.cookie_httponly','1')`, `ini_set('session.cookie_samesite','Lax')`. Must NOT break PHPUnit (guard with `PHP_SAPI !== 'cli'` and `session_status() === PHP_SESSION_NONE`). Confirm `display_errors` is `0` when not debug (already the case — just verify, no change unless wrong).
- `.htaccess`: add a best-effort `php_flag display_errors Off` is unreliable on FPM — instead document; for disabled functions WebSupport controls php.ini, so add a comment noting it's an infra task (B6 fail2ban / pm.max_children / disable_functions are owner/WebSupport — list in report, do not fake).
- `private/cron/db-backup.php`: CLI script that mysqldumps the configured DB (use config DB creds via `Kuko\Config`/PDO; on prod it's MySQL) to a timestamped `.sql.gz` under `private/logs/backups/` (gitignored), retaining the last N (e.g. 8). On SQLite dev, copy the sqlite file instead. Must not fatal if mysqldump absent — log + exit 1. Header comment: owner registers it weekly in WebSupport cron + downloads offsite.
- `docs/RECOVERY.md`: concise step-by-step DB/site recovery runbook (restore from WebSupport daily backup or the cron .sql.gz, redeploy via lftp, config.php from password manager, re-create `config/.htpasswd`).
- Test: `private/tests/unit/SessionHardeningTest.php` — string-assert App.php sets `samesite`/`httponly`/`secure`/`use_strict_mode`; the cron script lints.
- Full suite green (App.php session change must NOT break the 161 tests — verify carefully). Commit `feat: session cookie hardening + DB backup cron + recovery doc (roadmap B6/B5)`.

### S3-T8 — Regression + bookkeeping + deploy
- Full suite green; lint sweep; dev smoke (/,/rezervacia,/faq,/ochrana-udajov,/admin/login 200; /admin 302); keyboard smoke note (skip link appears on Tab, focus rings visible).
- Re-run `build-assets.php`; confirm all `.min.*` fresh (stale-min guard green).
- Check off A2/A3/A5/A6/U1/U3/U4/B5/B6 items in `roadmap-quality.md`; add Sprint 3 blockquote.
- git push origin main; lftp mirror public/ + private/; verify prod safety invariants (public / 503, robots Disallow) + key changed assets 200.
- Commit bookkeeping; push. Update `docs/plans/2026-05-15-quality-sprint1-STATUS.md` Sprint 3 section.

---
**Owner/manual (NOT built — report at end):** B3 PHPMailer CVE watch + Leaflet/reCAPTCHA review; B5 offsite backup download + config.php in password manager; B6 WebSupport infra (disable_functions, pm.max_children, fail2ban); B7 OWASP ZAP scan, manual pentest, subdomain takeover check, DNS DMARC/SPF/DKIM; A6 plain-language copy edits (via /admin content); plus prior go-live owner items (SMTP, reCAPTCHA browser test, GDPR cron registration, Lighthouse/axe, Google Business Profile, HSTS preload).
