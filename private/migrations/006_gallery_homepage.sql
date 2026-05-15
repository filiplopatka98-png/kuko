-- private/migrations/006_gallery_homepage.sql
-- Owner-curated homepage gallery selection (max 6, random fill on the homepage).
-- The migrate runner records applied files in the `migrations` table and skips
-- already-applied files, so this single ALTER is safe to re-run on populated prod.
ALTER TABLE gallery_photos ADD COLUMN on_homepage TINYINT(1) NOT NULL DEFAULT 0;
