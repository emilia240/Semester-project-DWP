-- =====================================================================
-- Add the time a comment was last edited (shown as "edited" next to the comment).
-- Run ONCE on an existing database, before deploying the new PHP code.
-- Back up the database first.
-- Select the right database in phpMyAdmin before importing this file.
-- A fresh database gets this column from 01_schema.sql instead.
-- =====================================================================

-- NULL means "never edited", so every old comment is correct without an UPDATE.
ALTER TABLE `Comment` ADD COLUMN `EditedAt` DATETIME NULL AFTER `CreatedAt`;
