-- Booking availability extension
CREATE TABLE IF NOT EXISTS packages (
    code             VARCHAR(20) PRIMARY KEY,
    name             VARCHAR(120) NOT NULL,
    duration_min     INT UNSIGNED NOT NULL,
    blocks_full_day  TINYINT(1) NOT NULL DEFAULT 0,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    sort_order       TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO packages (code, name, duration_min, blocks_full_day, sort_order) VALUES
  ('mini',   'KUKO MINI',           120, 0, 1),
  ('maxi',   'KUKO MAXI',           180, 0, 2),
  ('closed', 'Uzavretá spoločnosť', 240, 1, 3);

CREATE TABLE IF NOT EXISTS opening_hours (
    weekday      TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    is_open      TINYINT(1) NOT NULL DEFAULT 1,
    open_from    TIME NOT NULL DEFAULT '09:00:00',
    open_to      TIME NOT NULL DEFAULT '20:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO opening_hours (weekday, is_open, open_from, open_to) VALUES
  (0, 1, '09:00:00', '20:00:00'),
  (1, 1, '09:00:00', '20:00:00'),
  (2, 1, '09:00:00', '20:00:00'),
  (3, 1, '09:00:00', '20:00:00'),
  (4, 1, '09:00:00', '20:00:00'),
  (5, 1, '09:00:00', '20:00:00'),
  (6, 1, '09:00:00', '20:00:00');

CREATE TABLE IF NOT EXISTS blocked_periods (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_from    DATE NOT NULL,
    date_to      DATE NOT NULL,
    time_from    TIME NULL,
    time_to      TIME NULL,
    reason       VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date_range (date_from, date_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    setting_key  VARCHAR(60) PRIMARY KEY,
    value        TEXT NOT NULL,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (setting_key, value) VALUES
  ('buffer_min',         '30'),
  ('horizon_days',       '180'),
  ('lead_hours',         '24'),
  ('slot_increment_min', '30');

ALTER TABLE reservations
  ADD COLUMN confirmed_at      DATETIME NULL AFTER status,
  ADD COLUMN cancelled_at      DATETIME NULL AFTER confirmed_at,
  ADD COLUMN cancelled_reason  VARCHAR(255) NULL AFTER cancelled_at;
