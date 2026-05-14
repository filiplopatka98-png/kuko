-- KUKO detský svet — initial schema
CREATE TABLE IF NOT EXISTS reservations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package       ENUM('mini','maxi','closed') NOT NULL,
    wished_date   DATE NOT NULL,
    wished_time   TIME NOT NULL,
    kids_count    TINYINT UNSIGNED NOT NULL,
    name          VARCHAR(120) NOT NULL,
    phone         VARCHAR(40)  NOT NULL,
    email         VARCHAR(180) NOT NULL,
    note          TEXT NULL,
    status        ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    ip_hash       CHAR(64) NOT NULL,
    recaptcha_score DECIMAL(3,2) NULL,
    user_agent    VARCHAR(255) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_date (status, wished_date),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_actions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user    VARCHAR(60) NOT NULL,
    action        VARCHAR(40) NOT NULL,
    target_table  VARCHAR(40) NOT NULL,
    target_id     INT UNSIGNED NOT NULL,
    payload_json  JSON NULL,
    ip_hash       CHAR(64) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_target (target_table, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
