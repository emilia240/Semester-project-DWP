-- =====================================================================
-- 02_test_users.sql   TEST DATA ONLY - LOCAL COMPUTER ONLY. NEVER import this on the live server!
-- Both are already e-mail-confirmed, so you can log in straight away.
--   admin@example.com   password: Admin#2026test    (role admin)
--   member@example.com  password: Member#2026test   (role member)
-- The passwords are stored as bcrypt hashes (made with password_hash()).
-- =====================================================================

USE dwp_semester;

INSERT IGNORE INTO `User`
    (`RoleID`, `Username`, `FirstName`, `LastName`, `Email`, `Birthdate`, `PasswordHash`, `EmailVerifiedAt`, `HumanMadeConfirmedAt`, `Tagline`)
VALUES
    (2, 'siteadmin', 'Site', 'Owner', 'admin@example.com', '1990-01-01', '$2y$10$Dgbff9zL.5MJl0CwsUuVb.2MjaC1EGHSUawH9bfnnILQUxPEniAJW', NOW(), NOW(), 'Keeping the garden tidy'),
    (1, 'mira', 'Mira', 'Holm', 'member@example.com', '2000-05-12', '$2y$10$vMjeMeaesWAWG.3fj4iW8uAKKOJSpcNugMHPmK5K5rkmeIMIk7y9G', NOW(), NOW(), 'Ceramicist from Odense');
