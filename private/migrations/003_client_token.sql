-- Client-side read-only status link
ALTER TABLE reservations
  ADD COLUMN view_token CHAR(32) NULL AFTER ip_hash;

CREATE UNIQUE INDEX idx_view_token ON reservations (view_token);
