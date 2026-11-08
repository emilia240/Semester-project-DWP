-- =====================================================================
-- Posts: let a DRAFT be saved without the human-made tick.
-- Run this once on an EXISTING database (select the database in phpMyAdmin first, then import this file).
-- A fresh database gets the same change from 01_schema.sql instead. Back up first if real posts exist.
-- Before: HumanMadeConfirmedAt was NOT NULL, so a draft could not be saved (the tick is only asked when you publish).
-- After:  it may be empty, but a PUBLISHED post (IsDraft = 0) must still have it (the CHECK).
-- =====================================================================
ALTER TABLE `Post` MODIFY `HumanMadeConfirmedAt` DATETIME NULL;

-- Existing posts that are already published keep their date, so the CHECK is satisfied for them.
ALTER TABLE `Post`
    ADD CONSTRAINT ck_post_published_confirmed CHECK (`IsDraft` = 1 OR `HumanMadeConfirmedAt` IS NOT NULL);
