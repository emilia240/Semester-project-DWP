-- =====================================================================
-- 09_views_ban_rule.sql   change the 3 views: a private profile no longer hides posts, and a ban that has run out no longer hides them
--
-- For a database that already exists (local AND Simply). A fresh import does not need this file: 03_views_triggers.sql already has the change.
-- LOCAL: phpMyAdmin -> click the database dwp_semester -> Import -> this file.
-- ON SIMPLY: click your database first, then Import. There is no USE line, so nothing to delete.
-- Safe to run again: each view is dropped first. No table or column changes, no data is lost (a view holds no data).
--
-- Before: the views needed IsBanned = 0 AND IsProfilePublic = 1.
-- After : the views need (IsBanned = 0 OR BannedUntil <= NOW()). This is the rule PostModel already uses for the feed.
-- =====================================================================

DROP VIEW IF EXISTS hot_posts;
DROP VIEW IF EXISTS sticky_posts;
DROP VIEW IF EXISTS maker_of_the_week;

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
WHERE p.`IsDraft` = 0 AND (u.`IsBanned` = 0 OR u.`BannedUntil` <= NOW())
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
  AND p.`IsDraft` = 0 AND (u.`IsBanned` = 0 OR u.`BannedUntil` <= NOW())
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
WHERE p.`IsDraft` = 0 AND (u.`IsBanned` = 0 OR u.`BannedUntil` <= NOW())
HAVING WeekScore > 0
ORDER BY WeekScore DESC, p.`UploadedAt` DESC
LIMIT 1;
