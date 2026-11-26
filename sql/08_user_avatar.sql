-- =====================================================================
-- Add the profile photo (avatar) of a member.
-- Run ONCE on an existing database, before deploying the new PHP code.
-- Back up the database first.
-- Select the right database in phpMyAdmin before importing this file.
-- A fresh database gets this column from 01_schema.sql instead.
-- =====================================================================

-- The name of the picture file in uploads/ (32 random characters + .jpg = 36). NULL means "no photo":
-- the site then shows the initials, so every old member is correct without an UPDATE.
ALTER TABLE `User` ADD COLUMN `AvatarFile` VARCHAR(40) NULL AFTER `Badge`;
