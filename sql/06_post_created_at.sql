-- =====================================================================
-- Add the creation date used by the new-post limit.
-- Run ONCE on an existing database, before deploying the new PHP code.
-- Back up the database first and pause post creation and editing while this runs.
-- Select the right database in phpMyAdmin before importing this file.
-- A fresh database gets this column and index from 01_schema.sql instead.
-- =====================================================================

-- Start with an empty creation date, so we can fill it for the old posts.
ALTER TABLE `Post` ADD COLUMN `CreatedAt` DATETIME NULL AFTER `PostDescription`;

-- The original creation time was not stored. UploadedAt is the best available
-- estimate, but it may be the publication time for an old published draft.
UPDATE `Post` SET `CreatedAt` = `UploadedAt` WHERE `CreatedAt` IS NULL;

-- New posts get the database's current time. Publishing a draft will not change it.
-- The index helps the rate-limit query count one member's recent posts.
ALTER TABLE `Post`
    MODIFY `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD KEY idx_post_user_created (`UserID`, `CreatedAt`);
