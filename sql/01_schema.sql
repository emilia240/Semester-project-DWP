-- =====================================================================
-- 01_schema.sql   the WHOLE database of To Be Human: 24 tables, from the RDM (docs/rdm.drawio and docs/rdm.md)
--
-- LOCAL (XAMPP): phpMyAdmin -> Import (nothing selected) -> choose this file -> Go.
-- ON SIMPLY: delete the 4 lines between the LOCAL-ONLY markers below. There the database already
--            exists, so you first click it in phpMyAdmin and then import this file.
-- THEN import 03_views_triggers.sql (the 3 views and the triggers) the same way.
-- 02_test_users.sql is LOCAL ONLY (never on the live server).
--
-- !! This file DROPS every table and builds it again, so all data is lost. That is fine while the site only has
-- !! test accounts. When real users exist, NEVER run it again: change tables with small ALTER TABLE files instead.
--
-- Notes to self:
--  * utf8mb4 = real UTF-8 (emoji, ae/oe/aa). Collation utf8mb4_bin, as the teacher (Kim) prefers ("utf and bin").
--    _bin compares the exact bytes, so it is case-sensitive: "Mira" and "mira" are DIFFERENT values, even under
--    UNIQUE. So PHP lower-cases e-mails before saving and checking, and checks usernames case-insensitively
--    (WHERE LOWER(Username) = LOWER(?)) before inserting, otherwise two people could look the same.
--    Every table sets its own charset and collation below, so it does not matter which collation the database has.
--  * No ENUM (teacher: "a table inside a table"). Role has its own table; status words are VARCHAR checked in PHP.
--  * Derived data is NOT stored (3NF): age (from Birthdate), like counts, hot posts, maker of the week -> views.
--  * Names: PK = TableNameID, FK = same name as the PK it points to (RoleID), except where the role matters
--    (FollowerID / FollowedID, ReporterID / HandlerID, RecipientID / ActorID, SetUpByUserID, ParentCommentID).
--  * ON DELETE: CASCADE = what a user owns disappears with the user. RESTRICT = refuse the delete.
--    SET NULL = keep the row, empty the link (only for optional foreign keys).
--  * Column sizes are chosen per column (not 255 everywhere): short words get short columns, long text gets TEXT.
--  * Every FK column has an index (InnoDB makes one by itself, here they are named so the script shows them).
--  * QUESTION FOR SOREN: all ids, Position and SortOrder are INT / TINYINT / SMALLINT UNSIGNED. UNSIGNED means the
--    number can never be negative, so the positive range doubles (INT: 0 to about 4.29 billion instead of
--    -2.1 to +2.1 billion). It is MySQL/MariaDB only, not standard SQL. Keep UNSIGNED, or use plain INT?
--  * QUESTION FOR SOREN: the LOCAL-ONLY block does DROP DATABASE + CREATE DATABASE (like the restaurant assignment).
--    On Simply that block is deleted and every table gets its own DROP TABLE IF EXISTS instead, because the hosting
--    gives one ready-made database. Is that the best way, or would he do it differently?
--  * Every table and column name is written WITH backticks, so names like Role or Notification can never clash with
--    a keyword (and the editor does not colour them red). Constraint and index names stay plain.
-- =====================================================================

-- >>> LOCAL-ONLY (delete these lines on Simply) >>>
SET default_storage_engine = 'InnoDB';     -- InnoDB is needed for foreign keys
DROP DATABASE IF EXISTS dwp_semester;      -- remove the old version, if any
CREATE DATABASE dwp_semester CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
USE dwp_semester;                          -- switch to the new database
-- <<< LOCAL-ONLY <<<

-- Start clean (children before parents is not needed because the checks are switched off for a moment)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `FaqItem`;
DROP TABLE IF EXISTS `SiteSetting`;
DROP TABLE IF EXISTS `Notification`;
DROP TABLE IF EXISTS `Report`;
DROP TABLE IF EXISTS `UserSavedBoard`;
DROP TABLE IF EXISTS `BoardPost`;
DROP TABLE IF EXISTS `Board`;
DROP TABLE IF EXISTS `Follow`;
DROP TABLE IF EXISTS `CommentLike`;
DROP TABLE IF EXISTS `Comment`;
DROP TABLE IF EXISTS `PostLike`;
DROP TABLE IF EXISTS `PostTag`;
DROP TABLE IF EXISTS `Tag`;
DROP TABLE IF EXISTS `PostCategory`;
DROP TABLE IF EXISTS `PostImage`;
DROP TABLE IF EXISTS `Post`;
DROP TABLE IF EXISTS `UserChallenge`;
DROP TABLE IF EXISTS `Challenge`;
DROP TABLE IF EXISTS `UserCategory`;
DROP TABLE IF EXISTS `Category`;
DROP TABLE IF EXISTS `Token`;
DROP TABLE IF EXISTS `UserSocialLink`;
DROP TABLE IF EXISTS `User`;
DROP TABLE IF EXISTS `Role`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Role: member (default, low) and admin. Own table, as the teacher said (no ENUM).
-- RoleID 1 = member must stay the FIRST row (it is the default of User.RoleID).
-- ---------------------------------------------------------------------
CREATE TABLE `Role` (
    `RoleID`   TINYINT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    -- member, admin
    `RoleName` VARCHAR(20)      NOT NULL,
    UNIQUE KEY uq_role_name (`RoleName`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

INSERT INTO `Role` (`RoleID`, `RoleName`) VALUES (1, 'member'), (2, 'admin');

-- ---------------------------------------------------------------------
-- User: one row per account. Age is NOT stored, it is derived from Birthdate (3NF).
-- Join steps: 1 sign up, 2 crafts (rows in UserCategory), 3 guidelines (GuidelinesAcceptedAt).
-- ---------------------------------------------------------------------
CREATE TABLE `User` (
    `UserID`               INT UNSIGNED     AUTO_INCREMENT NOT NULL PRIMARY KEY,
    -- default 1 = member
    `RoleID`               TINYINT UNSIGNED NOT NULL DEFAULT 1,
    -- UNIQUE. _bin collation: 'Mira' and 'mira' are different, so PHP also checks the name case-insensitively
    `Username`             VARCHAR(50)      NOT NULL,
    `FirstName`            VARCHAR(110)     NOT NULL,
    `LastName`             VARCHAR(110)     NOT NULL,
    -- 190 so the UNIQUE index fits (utf8mb4). _bin is case-sensitive: PHP saves it in lower case
    `Email`                VARCHAR(190)     NOT NULL,
    `Birthdate`            DATE             NOT NULL,
    -- bcrypt hash, never the password (255 leaves room for newer algorithms)
    `PasswordHash`         VARCHAR(255)     NOT NULL,
    -- the database fills it in
    `CreatedAt`            TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- NULL = not confirmed yet
    `EmailVerifiedAt`      DATETIME         NULL,
    -- 0 = no, 1 = yes
    `IsBanned`             TINYINT(1)       NOT NULL DEFAULT 0,
    -- NULL + IsBanned = permanent
    `BannedUntil`          DATETIME         NULL,
    -- "my work is human-made" checkbox at sign-up
    `HumanMadeConfirmedAt` DATETIME         NULL,
    -- join step 3; NULL = guidelines not accepted yet
    `GuidelinesAcceptedAt` DATETIME         NULL,
    -- short line under the name
    `Tagline`              VARCHAR(80)      NULL,
    `AboutText`            TEXT             NULL,
    -- fixed list in PHP, NULL = none
    `Badge`                VARCHAR(30)      NULL,
    -- 1 = public
    `IsProfilePublic`      TINYINT(1)       NOT NULL DEFAULT 1,
    -- 1 = show
    `ShowFollowerCount`    TINYINT(1)       NOT NULL DEFAULT 1,
    -- 1 = show
    `ShowLikes`            TINYINT(1)       NOT NULL DEFAULT 1,
    -- 1 = the triggers may create notifications for this user
    `NotificationsOn`      TINYINT(1)       NOT NULL DEFAULT 1,
    -- 'light' or 'dark'
    `Theme`                VARCHAR(10)      NOT NULL DEFAULT 'light',
    UNIQUE KEY uq_user_username (`Username`),
    UNIQUE KEY uq_user_email (`Email`),
    KEY idx_user_role (`RoleID`),
    CONSTRAINT fk_user_role FOREIGN KEY (`RoleID`) REFERENCES `Role` (`RoleID`) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- UserSocialLink: the multivalued attribute SocialLink of User (a user can have several links).
-- User 1 --has-- N UserSocialLink. Deleted together with the user.
-- ---------------------------------------------------------------------
CREATE TABLE `UserSocialLink` (
    `SocialLinkID` INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    `UserID`       INT UNSIGNED NOT NULL,
    `Url`          VARCHAR(255) NOT NULL,
    KEY idx_usersociallink_user (`UserID`),
    CONSTRAINT fk_usersociallink_user FOREIGN KEY (`UserID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- Token: e-mail confirmation and password reset links. Only the HASH of the token is stored, like a password:
-- if somebody steals the database they cannot use the links. User 1 --owns-- N Token. TokenType is
-- 'verify_email' or 'reset_password' (checked in PHP). Deleting a user deletes their tokens.
-- ---------------------------------------------------------------------
CREATE TABLE `Token` (
    `TokenID`   INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    `UserID`    INT UNSIGNED NOT NULL,
    -- sha256 as 64 hex characters
    `TokenHash` CHAR(64)     NOT NULL,
    `TokenType` VARCHAR(20)  NOT NULL,
    `ExpiresAt` DATETIME     NOT NULL,
    UNIQUE KEY uq_token_hash (`TokenHash`),
    KEY idx_token_user (`UserID`),
    CONSTRAINT fk_token_user FOREIGN KEY (`UserID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- Category: the crafts (Ceramics, Textiles ...). Used in join step 2 and as the categories of a post. Filled in below.
-- ---------------------------------------------------------------------
CREATE TABLE `Category` (
    `CategoryID`   INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    `CategoryName` VARCHAR(40)  NOT NULL,
    UNIQUE KEY uq_category_name (`CategoryName`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

INSERT INTO `Category` (`CategoryName`) VALUES
    ('Ceramics'),
    ('Textiles'),
    ('Woodwork'),
    ('Painting'),
    ('Drawing'),
    ('Jewellery'),
    ('Knitting and crochet'),
    ('Embroidery'),
    ('Printmaking'),
    ('Leatherwork'),
    ('Glass'),
    ('Paper crafts'),
    ('Sculpture');

-- ---------------------------------------------------------------------
-- UserCategory: junction table User M:N Category = the crafts a user picked in join step 2.
-- The PK is the two columns together, so the same craft cannot be picked twice.
-- ---------------------------------------------------------------------
CREATE TABLE `UserCategory` (
    `UserID`     INT UNSIGNED NOT NULL,
    `CategoryID` INT UNSIGNED NOT NULL,
    -- the two columns together are the key
    CONSTRAINT PK_UserCategory PRIMARY KEY (`UserID`, `CategoryID`),
    KEY idx_usercategory_category (`CategoryID`),
    CONSTRAINT fk_usercategory_user FOREIGN KEY (`UserID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    CONSTRAINT fk_usercategory_category FOREIGN KEY (`CategoryID`) REFERENCES `Category` (`CategoryID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- Challenge: the weekly challenge, set up by an admin (SetUpByUserID).
-- RESTRICT: an admin who set up a challenge cannot be deleted while the challenge exists.
-- ---------------------------------------------------------------------
CREATE TABLE `Challenge` (
    `ChallengeID`   INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    -- the admin who set it up
    `SetUpByUserID` INT UNSIGNED NOT NULL,
    `ChallengeName` VARCHAR(80)  NOT NULL,
    `Description`   TEXT         NOT NULL,
    `StartDate`     DATE         NOT NULL,
    `EndDate`       DATE         NOT NULL,
    KEY idx_challenge_setupbyuser (`SetUpByUserID`),
    CONSTRAINT fk_challenge_setupbyuser FOREIGN KEY (`SetUpByUserID`) REFERENCES `User` (`UserID`) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- UserChallenge: junction table User M:N Challenge = which members joined which challenge.
-- ---------------------------------------------------------------------
CREATE TABLE `UserChallenge` (
    `UserID`      INT UNSIGNED NOT NULL,
    `ChallengeID` INT UNSIGNED NOT NULL,
    `JoinedAt`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- the two columns together are the key
    CONSTRAINT PK_UserChallenge PRIMARY KEY (`UserID`, `ChallengeID`),
    KEY idx_userchallenge_challenge (`ChallengeID`),
    CONSTRAINT fk_userchallenge_user FOREIGN KEY (`UserID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    CONSTRAINT fk_userchallenge_challenge FOREIGN KEY (`ChallengeID`) REFERENCES `Challenge` (`ChallengeID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- Post: one artwork / project. Several images per post are in PostImage.
-- ChallengeID is optional (partial participation): NULL = not part of a challenge.
-- ON DELETE SET NULL: if a challenge is deleted the post stays, only the link is emptied.
-- ---------------------------------------------------------------------
CREATE TABLE `Post` (
    `PostID`               INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    `UserID`               INT UNSIGNED NOT NULL,
    `ChallengeID`          INT UNSIGNED NULL,
    `PostTitle`            VARCHAR(100) NOT NULL,
    `PostDescription`      TEXT         NULL,
    `UploadedAt`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- text for screen readers
    `AltText`              VARCHAR(200) NULL,
    -- the "this is human-made" tick on THIS post
    `HumanMadeConfirmedAt` DATETIME     NOT NULL,
    -- 1 = draft (only the owner sees it), 0 = published
    `IsDraft`              TINYINT(1)   NOT NULL DEFAULT 1,
    -- 'pending', 'passed' or 'flagged' (checked in PHP)
    `AiCheckStatus`        VARCHAR(15)  NOT NULL DEFAULT 'pending',
    KEY idx_post_challenge (`ChallengeID`),
    -- newest posts first (Explore, home feed)
    KEY idx_post_uploaded (`UploadedAt`),
    -- the posts of one profile, newest first (also serves the FK on UserID)
    KEY idx_post_user_uploaded (`UserID`, `UploadedAt`),
    CONSTRAINT fk_post_user FOREIGN KEY (`UserID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    CONSTRAINT fk_post_challenge FOREIGN KEY (`ChallengeID`) REFERENCES `Challenge` (`ChallengeID`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- PostImage: the images of a post. Position 1 = the cover, the rest are the step photos (work in progress).
-- Deleted with the post. The FILE on disk is deleted by PHP: read the file names, delete the rows, commit, THEN unlink().
-- ---------------------------------------------------------------------
CREATE TABLE `PostImage` (
    `ImageID`  INT UNSIGNED     AUTO_INCREMENT NOT NULL PRIMARY KEY,
    `PostID`   INT UNSIGNED     NOT NULL,
    -- random file name made by PHP, not the visitor's file name
    `FileName` VARCHAR(80)      NOT NULL,
    -- 1 = cover
    `Position` TINYINT UNSIGNED NOT NULL,
    KEY idx_postimage_post (`PostID`),
    CONSTRAINT fk_postimage_post FOREIGN KEY (`PostID`) REFERENCES `Post` (`PostID`) ON DELETE CASCADE,
    -- one image per position in a post
    UNIQUE KEY uq_postimage_position (`PostID`, `Position`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- PostCategory: junction table Post M:N Category. 1 to 3 per post, checked in PHP.
-- ---------------------------------------------------------------------
CREATE TABLE `PostCategory` (
    `PostID`     INT UNSIGNED NOT NULL,
    `CategoryID` INT UNSIGNED NOT NULL,
    -- the two columns together are the key
    CONSTRAINT PK_PostCategory PRIMARY KEY (`PostID`, `CategoryID`),
    KEY idx_postcategory_category (`CategoryID`),
    CONSTRAINT fk_postcategory_post FOREIGN KEY (`PostID`) REFERENCES `Post` (`PostID`) ON DELETE CASCADE,
    CONSTRAINT fk_postcategory_category FOREIGN KEY (`CategoryID`) REFERENCES `Category` (`CategoryID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- Tag: free words used for searching. Tags are NOT categories.
-- ---------------------------------------------------------------------
CREATE TABLE `Tag` (
    `TagID`   INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    `TagName` VARCHAR(30)  NOT NULL,
    UNIQUE KEY uq_tag_name (`TagName`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- PostTag: junction table Post M:N Tag.
-- ---------------------------------------------------------------------
CREATE TABLE `PostTag` (
    `PostID` INT UNSIGNED NOT NULL,
    `TagID`  INT UNSIGNED NOT NULL,
    -- the two columns together are the key
    CONSTRAINT PK_PostTag PRIMARY KEY (`PostID`, `TagID`),
    KEY idx_posttag_tag (`TagID`),
    CONSTRAINT fk_posttag_post FOREIGN KEY (`PostID`) REFERENCES `Post` (`PostID`) ON DELETE CASCADE,
    CONSTRAINT fk_posttag_tag FOREIGN KEY (`TagID`) REFERENCES `Tag` (`TagID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- PostLike: junction table User M:N Post. One row = one user likes one post. The PK stops a double like.
-- ---------------------------------------------------------------------
CREATE TABLE `PostLike` (
    `UserID`  INT UNSIGNED NOT NULL,
    `PostID`  INT UNSIGNED NOT NULL,
    `LikedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- the two columns together are the key
    CONSTRAINT PK_PostLike PRIMARY KEY (`UserID`, `PostID`),
    -- likes of a post in a time window: used by the views hot_posts and sticky_posts (also serves the FK on PostID)
    KEY idx_postlike_post_liked (`PostID`, `LikedAt`),
    CONSTRAINT fk_postlike_user FOREIGN KEY (`UserID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    CONSTRAINT fk_postlike_post FOREIGN KEY (`PostID`) REFERENCES `Post` (`PostID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- Comment: a comment on a post. A reply points to its parent comment in the SAME table (recursive relationship).
-- ParentCommentID NULL = a normal comment. PostID is kept on replies too, so the comments of a post load with one query
-- (known and chosen: a reply's post could also be found through its parent, see the 3NF note in docs/rdm.md).
-- ---------------------------------------------------------------------
CREATE TABLE `Comment` (
    `CommentID`       INT UNSIGNED  AUTO_INCREMENT NOT NULL PRIMARY KEY,
    `PostID`          INT UNSIGNED  NOT NULL,
    `UserID`          INT UNSIGNED  NOT NULL,
    `ParentCommentID` INT UNSIGNED  NULL,
    -- max 1000 characters (PHP checks it too)
    `CommentText`     VARCHAR(1000) NOT NULL,
    `CreatedAt`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_comment_user (`UserID`),
    KEY idx_comment_parentcomment (`ParentCommentID`),
    -- the comments under one post, in order (also serves the FK on PostID)
    KEY idx_comment_post_created (`PostID`, `CreatedAt`),
    CONSTRAINT fk_comment_post FOREIGN KEY (`PostID`) REFERENCES `Post` (`PostID`) ON DELETE CASCADE,
    CONSTRAINT fk_comment_user FOREIGN KEY (`UserID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    CONSTRAINT fk_comment_parentcomment FOREIGN KEY (`ParentCommentID`) REFERENCES `Comment` (`CommentID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- CommentLike: junction table User M:N Comment.
-- ---------------------------------------------------------------------
CREATE TABLE `CommentLike` (
    `UserID`    INT UNSIGNED NOT NULL,
    `CommentID` INT UNSIGNED NOT NULL,
    `LikedAt`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- the two columns together are the key
    CONSTRAINT PK_CommentLike PRIMARY KEY (`UserID`, `CommentID`),
    KEY idx_commentlike_comment (`CommentID`),
    CONSTRAINT fk_commentlike_user FOREIGN KEY (`UserID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    CONSTRAINT fk_commentlike_comment FOREIGN KEY (`CommentID`) REFERENCES `Comment` (`CommentID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- Follow: recursive M:N, User follows User. FollowerID = who follows, FollowedID = who is followed.
-- The CHECK stops a user from following themselves (the PHP checks it too).
-- ---------------------------------------------------------------------
CREATE TABLE `Follow` (
    `FollowerID` INT UNSIGNED NOT NULL,
    `FollowedID` INT UNSIGNED NOT NULL,
    `FollowedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- the two columns together are the key
    CONSTRAINT PK_Follow PRIMARY KEY (`FollowerID`, `FollowedID`),
    KEY idx_follow_followed (`FollowedID`),
    CONSTRAINT fk_follow_follower FOREIGN KEY (`FollowerID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    CONSTRAINT fk_follow_followed FOREIGN KEY (`FollowedID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    -- nobody follows themselves
    CONSTRAINT ck_follow_not_self CHECK (`FollowerID` <> `FollowedID`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- Board: a collection of posts owned by one user. IsDefault = 1 is "My saved", created by a trigger (03_views_triggers.sql).
-- ---------------------------------------------------------------------
CREATE TABLE `Board` (
    `BoardID`   INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    `UserID`    INT UNSIGNED NOT NULL,
    `BoardName` VARCHAR(50)  NOT NULL,
    -- 1 = "My saved"
    `IsDefault` TINYINT(1)   NOT NULL DEFAULT 0,
    -- 0 = private
    `IsPublic`  TINYINT(1)   NOT NULL DEFAULT 0,
    `CreatedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_board_user (`UserID`),
    CONSTRAINT fk_board_user FOREIGN KEY (`UserID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- BoardPost: junction table Board M:N Post = which posts are on which board.
-- ---------------------------------------------------------------------
CREATE TABLE `BoardPost` (
    `BoardID` INT UNSIGNED NOT NULL,
    `PostID`  INT UNSIGNED NOT NULL,
    `AddedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- the two columns together are the key
    CONSTRAINT PK_BoardPost PRIMARY KEY (`BoardID`, `PostID`),
    KEY idx_boardpost_post (`PostID`),
    CONSTRAINT fk_boardpost_board FOREIGN KEY (`BoardID`) REFERENCES `Board` (`BoardID`) ON DELETE CASCADE,
    CONSTRAINT fk_boardpost_post FOREIGN KEY (`PostID`) REFERENCES `Post` (`PostID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- UserSavedBoard: junction table User M:N Board = boards that a user saved from somebody else.
-- ---------------------------------------------------------------------
CREATE TABLE `UserSavedBoard` (
    `UserID`  INT UNSIGNED NOT NULL,
    `BoardID` INT UNSIGNED NOT NULL,
    `SavedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- the two columns together are the key
    CONSTRAINT PK_UserSavedBoard PRIMARY KEY (`UserID`, `BoardID`),
    KEY idx_usersavedboard_board (`BoardID`),
    CONSTRAINT fk_usersavedboard_user FOREIGN KEY (`UserID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    CONSTRAINT fk_usersavedboard_board FOREIGN KEY (`BoardID`) REFERENCES `Board` (`BoardID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- Report: a user reports a post OR a comment OR a user. Exactly ONE of PostID, CommentID, ReportedUserID is filled
-- (CHECK below). HandlerID = the admin who handled it, NULL until then. ResolvedAt is filled by a trigger.
-- ---------------------------------------------------------------------
CREATE TABLE `Report` (
    `ReportID`       INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    `ReporterID`     INT UNSIGNED NOT NULL,
    `HandlerID`      INT UNSIGNED NULL,
    `PostID`         INT UNSIGNED NULL,
    `CommentID`      INT UNSIGNED NULL,
    `ReportedUserID` INT UNSIGNED NULL,
    `Reason`         VARCHAR(500) NOT NULL,
    -- 'open', 'resolved' or 'dismissed'
    `Status`         VARCHAR(15)  NOT NULL DEFAULT 'open',
    `CreatedAt`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- NULL until handled (set by a trigger)
    `ResolvedAt`     DATETIME     NULL,
    KEY idx_report_reporter (`ReporterID`),
    KEY idx_report_handler (`HandlerID`),
    KEY idx_report_post (`PostID`),
    KEY idx_report_comment (`CommentID`),
    KEY idx_report_reporteduser (`ReportedUserID`),
    -- the admin list of open reports
    KEY idx_report_status (`Status`, `CreatedAt`),
    CONSTRAINT fk_report_reporter FOREIGN KEY (`ReporterID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    CONSTRAINT fk_report_handler FOREIGN KEY (`HandlerID`) REFERENCES `User` (`UserID`) ON DELETE SET NULL,
    CONSTRAINT fk_report_post FOREIGN KEY (`PostID`) REFERENCES `Post` (`PostID`) ON DELETE CASCADE,
    CONSTRAINT fk_report_comment FOREIGN KEY (`CommentID`) REFERENCES `Comment` (`CommentID`) ON DELETE CASCADE,
    CONSTRAINT fk_report_reporteduser FOREIGN KEY (`ReportedUserID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    -- exactly one target
    CONSTRAINT ck_report_one_target CHECK ((`PostID` IS NOT NULL) + (`CommentID` IS NOT NULL) + (`ReportedUserID` IS NOT NULL) = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- Notification: something happened to you (comment, reply, new follower). Never for likes.
-- Created by triggers (03_views_triggers.sql). A follow notification has no comment, so CommentID is NULL.
-- The post is found through the comment (3NF: no PostID here).
-- ---------------------------------------------------------------------
CREATE TABLE `Notification` (
    `NotificationID`   INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY,
    `RecipientID`      INT UNSIGNED NOT NULL,
    `ActorID`          INT UNSIGNED NOT NULL,
    `CommentID`        INT UNSIGNED NULL,
    -- 'comment', 'reply' or 'follow'
    `NotificationType` VARCHAR(10)  NOT NULL,
    `CreatedAt`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- NULL = not read yet
    `ReadAt`           DATETIME     NULL,
    KEY idx_notification_actor (`ActorID`),
    KEY idx_notification_comment (`CommentID`),
    -- the bell: unread notifications of one user (also serves the FK on RecipientID)
    KEY idx_notification_recipient_read (`RecipientID`, `ReadAt`),
    CONSTRAINT fk_notification_recipient FOREIGN KEY (`RecipientID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    CONSTRAINT fk_notification_actor FOREIGN KEY (`ActorID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE,
    CONSTRAINT fk_notification_comment FOREIGN KEY (`CommentID`) REFERENCES `Comment` (`CommentID`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- ---------------------------------------------------------------------
-- SiteSetting: ONE row that the admin edits (site name, description, rules, contact, styling). The admin pages check in
-- PHP that the logged-in user has the admin role, so no relationship to User is needed. No ENUM and no raw CSS:
-- ThemeName and AccentColour are a choice from a fixed list, applied as CSS classes (strict CSP).
-- ---------------------------------------------------------------------
CREATE TABLE `SiteSetting` (
    -- always 1 (CHECK below)
    `SiteSettingID`       TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    `SiteName`            VARCHAR(60)      NOT NULL,
    `SiteDescription`     TEXT             NOT NULL,
    `RulesAndRegulations` TEXT             NOT NULL,
    `ContactEmail`        VARCHAR(190)     NOT NULL,
    `ContactPhone`        VARCHAR(20)      NULL,
    -- fixed list: 'tobehuman' or 'tobehuman-dark'
    `ThemeName`           VARCHAR(30)      NOT NULL DEFAULT 'tobehuman',
    -- fixed list, becomes a CSS class
    `AccentColour`        VARCHAR(20)      NOT NULL DEFAULT 'default',
    -- only ONE row can exist
    CONSTRAINT ck_site_one_row CHECK (`SiteSettingID` = 1)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;

-- The one row. The admin edits it later (Backend: description, rules, contact, styling).
INSERT INTO `SiteSetting` (`SiteSettingID`, `SiteName`, `SiteDescription`, `RulesAndRegulations`, `ContactEmail`, `ContactPhone`, `ThemeName`, `AccentColour`) VALUES
    (1, 'To Be Human',
     'A photo-sharing platform for human-made arts and crafts: no ads, no AI images, just people making things.',
     'Only share work that you made yourself with your own hands. No AI-generated images. Be kind in the comments. Do not post other people''s work as yours.',
     'hello@tobehuman.example', NULL, 'tobehuman', 'default');

-- ---------------------------------------------------------------------
-- FaqItem: questions and answers for the FAQ page. Edited by the admin. Stands alone.
-- ---------------------------------------------------------------------
CREATE TABLE `FaqItem` (
    `FaqID`     INT UNSIGNED      AUTO_INCREMENT NOT NULL PRIMARY KEY,
    `Question`  VARCHAR(200)      NOT NULL,
    `Answer`    TEXT              NOT NULL,
    -- 1, 2, 3 ... order on the page
    `SortOrder` SMALLINT UNSIGNED NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_bin;
