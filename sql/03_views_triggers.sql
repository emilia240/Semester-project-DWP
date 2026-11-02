-- =====================================================================
-- 03_views_triggers.sql   the 3 VIEWS and the TRIGGERS of To Be Human (exam: at least 2 views and 2 triggers used by the website)
--
-- Import order: 01_schema.sql first, then THIS file, then (local only) 02_test_users.sql.
--   (Import 02 after this file so the trigger gives the test users their default board too.)
-- LOCAL: phpMyAdmin -> click the database dwp_semester -> Import -> this file.
-- ON SIMPLY: delete the USE line below (you already clicked your database in phpMyAdmin).
--   If the import stops at a trigger, the database user may lack the TRIGGER privilege: ask Simply support.
-- Safe to run again: every object is dropped first (no data is lost, views and triggers hold no data).
--
-- Notes to self:
--  * A VIEW is a saved SELECT. PHP reads it like a table: SELECT * FROM hot_posts. Nobody picks "hot" or "maker of the week":
--    they are calculated, so nothing is stored twice (3NF) and nobody (not even the admin) can fake them.
--  * A TRIGGER is SQL the database runs BY ITSELF after/before an INSERT, UPDATE or DELETE. NEW = the new row, OLD = the old row.
--    Triggers are invisible in the PHP code, so every one is explained here and in the report.
--  * Only published posts (IsDraft = 0) of users who are not banned and have a public profile show up in the views.
-- =====================================================================

USE dwp_semester;      -- ON SIMPLY: delete this line

DROP VIEW IF EXISTS hot_posts;
DROP VIEW IF EXISTS sticky_posts;
DROP VIEW IF EXISTS maker_of_the_week;
DROP TRIGGER IF EXISTS trg_user_default_board;
DROP TRIGGER IF EXISTS trg_comment_notify;
DROP TRIGGER IF EXISTS trg_follow_notify;
DROP TRIGGER IF EXISTS trg_report_resolved;

-- ---------------------------------------------------------------------
-- VIEW hot_posts  (Hot feed on the home page and Explore)
-- HotScore = likes + comments in the last 3 days. Posts with score 0 are left out.
-- PHP: SELECT * FROM hot_posts ORDER BY HotScore DESC, UploadedAt DESC LIMIT 20;
-- ---------------------------------------------------------------------
CREATE VIEW hot_posts AS
SELECT p.`PostID`, p.`UserID`, p.`PostTitle`, p.`UploadedAt`,
       (SELECT COUNT(*) FROM `PostLike` l WHERE l.`PostID` = p.`PostID` AND l.`LikedAt` >= NOW() - INTERVAL 3 DAY)   AS LikesLast3Days,
       (SELECT COUNT(*) FROM `Comment`  c WHERE c.`PostID` = p.`PostID` AND c.`CreatedAt` >= NOW() - INTERVAL 3 DAY) AS CommentsLast3Days,
       (SELECT COUNT(*) FROM `PostLike` l WHERE l.`PostID` = p.`PostID` AND l.`LikedAt` >= NOW() - INTERVAL 3 DAY)
     + (SELECT COUNT(*) FROM `Comment`  c WHERE c.`PostID` = p.`PostID` AND c.`CreatedAt` >= NOW() - INTERVAL 3 DAY) AS HotScore
FROM `Post` p
JOIN `User` u ON u.`UserID` = p.`UserID`
WHERE p.`IsDraft` = 0 AND u.`IsBanned` = 0 AND u.`IsProfilePublic` = 1
HAVING HotScore > 0;

-- ---------------------------------------------------------------------
-- VIEW sticky_posts  ("Coolest this week": the 4 most liked posts of the last 7 days)
-- PHP: SELECT * FROM sticky_posts;
-- ---------------------------------------------------------------------
CREATE VIEW sticky_posts AS
SELECT p.`PostID`, p.`UserID`, p.`PostTitle`, p.`UploadedAt`, COUNT(*) AS LikesThisWeek
FROM `Post` p
JOIN `User` u    ON u.`UserID` = p.`UserID`
JOIN `PostLike` l  ON l.`PostID` = p.`PostID`
WHERE l.`LikedAt` >= NOW() - INTERVAL 7 DAY
  AND p.`IsDraft` = 0 AND u.`IsBanned` = 0 AND u.`IsProfilePublic` = 1
GROUP BY p.`PostID`, p.`UserID`, p.`PostTitle`, p.`UploadedAt`
ORDER BY LikesThisWeek DESC, p.`UploadedAt` DESC
LIMIT 4;

-- ---------------------------------------------------------------------
-- VIEW maker_of_the_week  (the maker of the post with the most likes + saves in the last 7 days; ONE row or none)
-- A "save" = the post was put on a board (BoardPost.AddedAt).
-- PHP: SELECT * FROM maker_of_the_week;
-- ---------------------------------------------------------------------
CREATE VIEW maker_of_the_week AS
SELECT u.`UserID`, u.`Username`, u.`Tagline`, p.`PostID`, p.`PostTitle`,
       (SELECT COUNT(*) FROM `PostLike`  l WHERE l.`PostID` = p.`PostID` AND l.`LikedAt`  >= NOW() - INTERVAL 7 DAY)
     + (SELECT COUNT(*) FROM `BoardPost` b WHERE b.`PostID` = p.`PostID` AND b.`AddedAt` >= NOW() - INTERVAL 7 DAY) AS WeekScore
FROM `Post` p
JOIN `User` u ON u.`UserID` = p.`UserID`
WHERE p.`IsDraft` = 0 AND u.`IsBanned` = 0 AND u.`IsProfilePublic` = 1
HAVING WeekScore > 0
ORDER BY WeekScore DESC, p.`UploadedAt` DESC
LIMIT 1;

-- ---------------------------------------------------------------------
-- TRIGGERS. The DELIMITER lines are only needed because the trigger bodies contain ";".
-- ---------------------------------------------------------------------
DELIMITER $$

-- TRIGGER 1: every new user gets the default board "My saved" (private).
-- Used by: sign-up (nothing in PHP), the Save button and the Saved page.
CREATE TRIGGER trg_user_default_board
AFTER INSERT ON `User`
FOR EACH ROW
BEGIN
    INSERT INTO `Board` (`UserID`, `BoardName`, `IsDefault`, `IsPublic`)
    VALUES (NEW.`UserID`, 'My saved', 1, 0);
END$$

-- TRIGGER 2: a new comment or reply creates a Notification for the right person. Never for likes.
--   comment on a post  -> the owner of the post
--   reply to a comment -> the owner of the parent comment
-- Nothing happens when you answer yourself or when the receiver switched notifications off (User.NotificationsOn).
-- Used by: the bell and the Notifications page.
CREATE TRIGGER trg_comment_notify
AFTER INSERT ON `Comment`
FOR EACH ROW
BEGIN
    DECLARE v_recipient INT UNSIGNED DEFAULT NULL;
    DECLARE v_type      VARCHAR(10);

    IF NEW.`ParentCommentID` IS NULL THEN
        SELECT `UserID` INTO v_recipient FROM `Post`    WHERE `PostID`    = NEW.`PostID`;
        SET v_type = 'comment';
    ELSE
        SELECT `UserID` INTO v_recipient FROM `Comment` WHERE `CommentID` = NEW.`ParentCommentID`;
        SET v_type = 'reply';
    END IF;

    IF v_recipient IS NOT NULL AND v_recipient <> NEW.`UserID`
       AND (SELECT `NotificationsOn` FROM `User` WHERE `UserID` = v_recipient) = 1 THEN
        INSERT INTO `Notification` (`RecipientID`, `ActorID`, `CommentID`, `NotificationType`)
        VALUES (v_recipient, NEW.`UserID`, NEW.`CommentID`, v_type);
    END IF;
END$$

-- TRIGGER 3: a new follower creates a Notification for the followed user (CommentID stays NULL).
CREATE TRIGGER trg_follow_notify
AFTER INSERT ON `Follow`
FOR EACH ROW
BEGIN
    IF (SELECT `NotificationsOn` FROM `User` WHERE `UserID` = NEW.`FollowedID`) = 1 THEN
        INSERT INTO `Notification` (`RecipientID`, `ActorID`, `CommentID`, `NotificationType`)
        VALUES (NEW.`FollowedID`, NEW.`FollowerID`, NULL, 'follow');
    END IF;
END$$

-- TRIGGER 4: when an admin closes a report (Status changes from 'open' to something else) ResolvedAt is filled in.
-- Used by: the admin report list.
CREATE TRIGGER trg_report_resolved
BEFORE UPDATE ON `Report`
FOR EACH ROW
BEGIN
    IF OLD.`Status` = 'open' AND NEW.`Status` <> 'open' AND NEW.`ResolvedAt` IS NULL THEN
        SET NEW.`ResolvedAt` = NOW();
    END IF;
END$$

DELIMITER ;
