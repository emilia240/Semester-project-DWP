-- =====================================================================
-- 01_schema.sql   (login + sign-up part of the database)
-- Local (XAMPP): phpMyAdmin -> Import (nothing selected) -> choose this file -> Go.
-- Run it again whenever the design changes: it drops and rebuilds everything (like the restaurant assignment).
-- ON SIMPLY: delete the 4 lines between the LOCAL-ONLY markers below. There the database already
-- exists, so you first click it in phpMyAdmin and then import this file. See SIMPLY_GUIDE.md.
-- Tables follow the ER diagram: Role, User, Token.
-- More tables (Post, Comment, Board ...) are added later in their own files.
--
-- Notes to self:
--  * utf8mb4 = real UTF-8 (emoji, æøå). Collation unicode_ci on purpose:
--    "Mira" and "mira" count as the SAME username (no impersonation).
--    (Teacher said "utf and bin": ask him if _bin is required.)
--  * No ENUM (teacher: "a table inside a table"). Role has its own table.
--  * Age is NOT stored, it is derived from Birthdate (3NF).
--  * CreatedAt has a DEFAULT, so the database fills it in by itself.
-- =====================================================================

-- >>> LOCAL-ONLY (delete these lines on Simply) >>>
SET default_storage_engine = 'InnoDB';     -- InnoDB is needed for foreign keys
DROP DATABASE IF EXISTS dwp_semester;      -- remove the old version, if any
CREATE DATABASE dwp_semester CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dwp_semester;                          -- switch to the new database
-- <<< LOCAL-ONLY <<<

-- ---------------------------------------------------------------------
-- Role: member (default, low) and admin. Own table, as the teacher said.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Role` (
    `RoleID`   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `RoleName` VARCHAR(20)      NOT NULL,
    PRIMARY KEY (`RoleID`),
    UNIQUE KEY `uq_role_name` (`RoleName`)
) ENGINE = InnoDB;

-- RoleID 1 = member must stay the FIRST row (it is the default below).
INSERT IGNORE INTO `Role` (`RoleID`, `RoleName`) VALUES (1, 'member'), (2, 'admin');

-- ---------------------------------------------------------------------
-- User
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `User` (
    `UserID`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `RoleID`               TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `Username`             VARCHAR(50)      NOT NULL,
    `FirstName`            VARCHAR(110)      NOT NULL,
    `LastName`             VARCHAR(110)      NOT NULL,
    `Email`                VARCHAR(190)     NOT NULL,
    `Birthdate`            DATE             NOT NULL,
    `PasswordHash`         VARCHAR(255)     NOT NULL,   -- bcrypt hash, never the password
    `CreatedAt`            TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `EmailVerifiedAt`      DATETIME         NULL,       -- NULL = not confirmed yet
    `IsBanned`             TINYINT(1)       NOT NULL DEFAULT 0,
    `BannedUntil`          DATETIME         NULL,       -- NULL + IsBanned = permanent
    `HumanMadeConfirmedAt` DATETIME         NULL,       -- "my work is human-made" checkbox
    `Tagline`              VARCHAR(80)      NULL,
    `AboutText`            TEXT             NULL,
    `IsProfilePublic`      TINYINT(1)       NOT NULL DEFAULT 1,
    `ShowFollowerCount`    TINYINT(1)       NOT NULL DEFAULT 1,
    `Theme`                VARCHAR(10)      NOT NULL DEFAULT 'light',
    PRIMARY KEY (`UserID`),
    UNIQUE KEY `uq_user_email`    (`Email`),
    UNIQUE KEY `uq_user_username` (`Username`),
    KEY `idx_user_role` (`RoleID`),
    CONSTRAINT `fk_user_role` FOREIGN KEY (`RoleID`) REFERENCES `Role` (`RoleID`)
) ENGINE = InnoDB;

-- ---------------------------------------------------------------------
-- Token: e-mail confirmation (and later password reset).
-- Only the HASH of the token is stored, like a password: if somebody
-- steals the database they cannot use the links.
-- User 1 --owns-- N Token (total participation: UserID is NOT NULL).
-- Deleting a user deletes their tokens (ON DELETE CASCADE).
-- TokenType is 'verify_email' or 'reset_password' (checked in PHP).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `Token` (
    `TokenID`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `UserID`    INT UNSIGNED NOT NULL,
    `TokenHash` CHAR(64)     NOT NULL,
    `TokenType` VARCHAR(20)  NOT NULL,
    `ExpiresAt` DATETIME     NOT NULL,
    PRIMARY KEY (`TokenID`),
    UNIQUE KEY `uq_token_hash` (`TokenHash`),
    KEY `idx_token_user` (`UserID`),
    CONSTRAINT `fk_token_user` FOREIGN KEY (`UserID`) REFERENCES `User` (`UserID`) ON DELETE CASCADE
) ENGINE = InnoDB;
