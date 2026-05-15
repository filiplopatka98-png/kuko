-- private/migrations/005_cms.sql
CREATE TABLE IF NOT EXISTS content_blocks (
    block_key     VARCHAR(80) PRIMARY KEY,
    label         VARCHAR(120) NOT NULL,
    content_type  ENUM('text','html','image') NOT NULL DEFAULT 'text',
    value         MEDIUMTEXT NOT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by    VARCHAR(60) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_photos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename    VARCHAR(180) NOT NULL,
    webp        VARCHAR(180) NULL,
    alt_text    VARCHAR(255) NOT NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_visible  TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE packages ADD COLUMN description     TEXT NULL;
ALTER TABLE packages ADD COLUMN price_text      VARCHAR(40) NULL;
ALTER TABLE packages ADD COLUMN kids_count_text VARCHAR(40) NULL;
ALTER TABLE packages ADD COLUMN duration_text   VARCHAR(40) NULL;
ALTER TABLE packages ADD COLUMN included_json   TEXT NULL;
ALTER TABLE packages ADD COLUMN accent_color    VARCHAR(20) NULL;
