# Booking availability — extension spec

- **Date:** 2026-05-14
- **Status:** Approved (extension of base spec `2026-05-14-kuko-detskysvet-design.md`)
- **Owner:** Filip Lopatka

## Goal

Rozšíriť rezervačný systém z „dopytového formulára" na **self-service booking**:

- Owner v admine definuje otváracie hodiny, blokované obdobia, trvanie balíčkov a globálne nastavenia (buffer, horizont, lead-time, slot increment).
- Klient v modale vidí iba **dostupné** time sloty pre zvolený dátum a balíček.
- Pending aj confirmed rezervácia **blokuje** slot. Cancelled ho uvoľní.
- Owner v admine môže potvrdiť / odmietnuť / **presunúť** / zrušiť rezerváciu.
- Klient cez web rezerváciu meniť ani rušiť nemôže — len telefonicky.

## Klúčové domain pravidlá

1. **1 oslavy súčasne.** Herňa je jeden priestor, parallel oslavy nedovolíme.
2. **CLOSED (Uzavretá spoločnosť) blokuje celý deň.** Aj keby trvala len 4 h, počas jej dňa nie je možná žiadna ďalšia rezervácia.
3. **Buffer** medzi rezerváciami sa pripočíta na koniec **pred-rezervácie** (nie pred). Príklad: MAXI 13:00–16:00 + buffer 30 min → ďalší slot smie začať od 16:30.
4. **Lead time:** klient nemôže rezervovať skôr ako *N* hodín od „teraz". Default 24h.
5. **Horizont:** klient nemôže rezervovať na dátum vzdialenejší ako *N* dní. Default 180.
6. **Slot increment:** voľné časy generujeme v *N* minútových krokoch (default 30) — start times sú vždy zarovnané na tento increment.
7. **Open hours per weekday:** každý deň v týždni má `is_open` flag + `open_from`/`open_to`.
8. **Blocked period:** môže byť celodenné (NULL časy) alebo časové okno v rámci dňa.

## Data model — migrácia `002`

```sql
-- Configurable per-package metadata (durations, day-blocking)
CREATE TABLE packages (
    code             VARCHAR(20) PRIMARY KEY,
    name             VARCHAR(120) NOT NULL,
    duration_min     INT UNSIGNED NOT NULL,
    blocks_full_day  TINYINT(1) NOT NULL DEFAULT 0,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    sort_order       TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO packages (code, name, duration_min, blocks_full_day, sort_order) VALUES
  ('mini',   'KUKO MINI',   120, 0, 1),
  ('maxi',   'KUKO MAXI',   180, 0, 2),
  ('closed', 'Uzavretá spoločnosť', 240, 1, 3);

-- Weekly opening hours (0=Sunday … 6=Saturday, ISO-like by day-of-week)
CREATE TABLE opening_hours (
    weekday      TINYINT UNSIGNED NOT NULL PRIMARY KEY,  -- 0..6
    is_open      TINYINT(1) NOT NULL DEFAULT 1,
    open_from    TIME NOT NULL DEFAULT '09:00:00',
    open_to      TIME NOT NULL DEFAULT '20:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO opening_hours (weekday, is_open, open_from, open_to) VALUES
  (0, 1, '09:00:00', '20:00:00'),
  (1, 1, '09:00:00', '20:00:00'),
  (2, 1, '09:00:00', '20:00:00'),
  (3, 1, '09:00:00', '20:00:00'),
  (4, 1, '09:00:00', '20:00:00'),
  (5, 1, '09:00:00', '20:00:00'),
  (6, 1, '09:00:00', '20:00:00');

-- Blocked periods (holidays, vacation, one-off restrictions)
CREATE TABLE blocked_periods (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_from    DATE NOT NULL,
    date_to      DATE NOT NULL,
    time_from    TIME NULL,    -- NULL = all-day on each date in range
    time_to      TIME NULL,
    reason       VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date_range (date_from, date_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Generic key/value settings
CREATE TABLE settings (
    `key`       VARCHAR(60) PRIMARY KEY,
    value       TEXT NOT NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (`key`, value) VALUES
  ('buffer_min',         '30'),
  ('horizon_days',       '180'),
  ('lead_hours',         '24'),
  ('slot_increment_min', '30');

-- Reservations: add lifecycle timestamps and cancel reason
ALTER TABLE reservations
  ADD COLUMN confirmed_at      DATETIME NULL AFTER status,
  ADD COLUMN cancelled_at      DATETIME NULL AFTER confirmed_at,
  ADD COLUMN cancelled_reason  VARCHAR(255) NULL AFTER cancelled_at;
```

## Availability algoritmus

```
available_slots(date D, package P) =
  if D < today + lead_hours OR D > today + horizon_days: return []
  if not opening_hours[weekday(D)].is_open: return []
  if exists CLOSED reservation on D (pending|confirmed): return []
  if exists blocked_periods covering whole D: return []

  hours = opening_hours[weekday(D)]  // open_from..open_to
  duration = packages[P].duration_min
  buffer = settings.buffer_min
  step = settings.slot_increment_min

  free_intervals = [(hours.open_from, hours.open_to)]
  // subtract blocked time windows on D
  for each blocked period B intersecting D:
    free_intervals = subtract(free_intervals, B.window_on(D))
  // subtract existing reservations (pending|confirmed) windows + buffer suffix
  for each R on D (status in pending,confirmed):
    r_start = R.wished_time
    r_end   = r_start + packages[R.package].duration_min
    if R.package = closed: return []  // already covered above, defensive
    free_intervals = subtract(free_intervals, (r_start, r_end + buffer))

  slots = []
  for each (a, b) in free_intervals:
    t = align_up(a, step)
    while t + duration ≤ b:
      slots.push(t)
      t += step
  return slots
```

Important: pred-reservation buffer sa pripočítava jednostranne k pravej hrane existujúcej rezervácie (tj. medzi „posledná stará" a „prvá nová" musí byť ≥ buffer). Tým aj `t + duration ≤ b` zachová priestor pre upratovanie.

## API

### `GET /api/availability?date=YYYY-MM-DD&package=mini|maxi|closed`

Response 200:
```json
{
  "date": "2026-06-15",
  "package": "mini",
  "duration_min": 120,
  "slots": ["10:00", "10:30", "11:00", "13:30", "14:00"]
}
```

Response 200 ak žiadne sloty (zatvorené, blokované, plné):
```json
{ "date": "2026-06-15", "package": "mini", "duration_min": 120, "slots": [], "reason": "closed_day" }
```

Reason hodnoty: `closed_day` / `before_lead` / `after_horizon` / `blocked_full_day` / `full`.

### `POST /api/reservation` — sprísnené

Pred insert-om volá Availability v transakcii (MySQL: `SELECT … FOR UPDATE` na `reservations` v danom dátume). Ak slot nie je dostupný → `409 Conflict` `{error: "slot_taken"}` a klient dostane jasnú správu.

## Admin pages

- `/admin/settings` — formulár 4 inputov (buffer_min, horizon_days, lead_hours, slot_increment_min).
- `/admin/opening-hours` — 7 riadkov (Po–Ne) s checkbox „otvorené" + 2 time inputmi.
- `/admin/blocked-periods` — list + create form (date_from, date_to, voliteľne time range, reason).
- `/admin/packages` — list + edit (name, duration_min, blocks_full_day, is_active).
- `/admin/calendar` — mesačný grid: každý deň zobrazí rezervácie ako farebné bunky podľa balíčka/statusu, blocked periods ako sivý overlay, dni mimo opening_hours sú stmavené. Naviguje sa cez `?month=YYYY-MM`.
- **Reservation detail** + tlačidlo **„Presunúť"** otvorí modal s date/time pickerom. Backend overí dostupnosť rovnakým spôsobom ako pri novej rezervácii.

## Client UX zmeny

V modale (`reservation-modal.php` + `reservation.js`):
1. Po výbere `package` + `wished_date` → JS volá `/api/availability` a vykreslí dostupné časy ako klikateľné chips.
2. `<input type="time">` sa nahradí skrytým fieldom + chip pickerom (`role="radiogroup"`).
3. Submit gate: zostáva závislý na cookie consent + reCAPTCHA, ale aj na zvolenom slote.
4. Žiadne tlačidlo „Zrušiť rezerváciu" v modal-i ani na webe (textom uvedené v autoreply maili: „Zrušenie alebo zmena termínu volajte +421 915 319 934").

## Out of scope (zachované z base specu)

- Žiadny self-service cancel/edit klientom.
- Žiadne SMS notifikácie.
- Žiadne online platby.

## Migration ordering

1. Migrácia `002` — nové tabuľky + ALTER reservations.
2. Spustiť `php private/migrations/run.php` na deploy-i.
3. Po deploy-i owner v `/admin/settings` skontroluje defaults a v `/admin/opening-hours` upraví podľa skutočnej prevádzky.
