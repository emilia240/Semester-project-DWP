<?php
/*
UserModel  =  everything about the `User` table. ALL the SQL for users lives in this file.

A MODEL is "anything that has to do with the database". The controller never writes SQL; it asks the model:
    $users = new UserModel($db);
    $row   = $users->findForLogin($email);
Why? If a column is renamed, you change ONE file instead of hunting through every page.
Every query uses a PREPARED STATEMENT: the values are sent separately from the SQL text, so a visitor can never
turn their input into SQL (that is SQL injection, lecture 9).
*/
class UserModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // The columns the login page needs, found by e-mail. Returns the row as an array, or null if there is no such e-mail.
    public function findForLogin(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT UserID, Username, PasswordHash, EmailVerifiedAt, IsBanned, BannedUntil
               FROM `User` WHERE Email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    // The full, fresh user row with the role name. Used by current_user() in includes/functions.php.
    public function findWithRole(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.UserID, u.Username, u.FirstName, u.LastName, u.Email, u.RoleID, r.RoleName,
                    u.EmailVerifiedAt, u.IsBanned, u.BannedUntil, u.Theme, u.GuidelinesAcceptedAt, u.AvatarFile
               FROM `User` u
               JOIN `Role` r ON r.RoleID = u.RoleID
              WHERE u.UserID = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /*
    One member's profile data for the profile page (ProfileController::show).
    On purpose it does NOT select the e-mail or the birthdate: the profile page never needs them
    (the owner sees them on the My account page), so they are never even loaded here.
    Returns null when there is no member with this id.
    */
    public function findProfile(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT UserID, Username, FirstName, LastName,
                    CreatedAt, IsBanned, BannedUntil,
                    Tagline, AboutText, Badge, AvatarFile, IsProfilePublic, ShowFollowerCount
               FROM `User`
              WHERE UserID = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return $row;
    }

    // The birthdate of a member as text like 1994-02-11 (empty text if there is no such member). Only My account shows it.
    public function birthdateOf(int $userId): string
    {
        $stmt = $this->db->prepare('SELECT Birthdate FROM `User` WHERE UserID = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $birthdate = $stmt->fetchColumn();
        if ($birthdate === false) {
            return '';
        }
        return (string)$birthdate;
    }

    // The crafts a member has picked (names, A to Z). Used for the little chips on the profile.
    public function craftsOf(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.CategoryName
               FROM `UserCategory` uc
               JOIN `Category` c ON c.CategoryID = uc.CategoryID
              WHERE uc.UserID = :id
              ORDER BY c.CategoryName'
        );
        $stmt->execute([':id' => $userId]);
        $names = [];
        foreach ($stmt->fetchAll() as $row) {
            $names[] = $row['CategoryName'];
        }
        return $names;
    }

    // How many members follow this member. (Follow = one row per follower.)
    public function countFollowers(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `Follow` WHERE FollowedID = :id');
        $stmt->execute([':id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    // How many members this member follows.
    public function countFollowing(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `Follow` WHERE FollowerID = :id');
        $stmt->execute([':id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    // Does this member follow that member? True or false. (One row in Follow = one "follow".)
    public function isFollowing(int $followerId, int $followedId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `Follow` WHERE FollowerID = :follower AND FollowedID = :followed');
        $stmt->execute([':follower' => $followerId, ':followed' => $followedId]);
        $howMany = (int)$stmt->fetchColumn();
        if ($howMany > 0) {
            return true;
        }
        return false;
    }

    // Follow: add one row to Follow. The database trigger then makes a "follow" notification (if the member wants them).
    public function follow(int $followerId, int $followedId): void
    {
        $stmt = $this->db->prepare('INSERT INTO `Follow` (FollowerID, FollowedID) VALUES (:follower, :followed)');
        $stmt->execute([':follower' => $followerId, ':followed' => $followedId]);
    }

    // Unfollow: remove that one row from Follow.
    public function unfollow(int $followerId, int $followedId): void
    {
        $stmt = $this->db->prepare('DELETE FROM `Follow` WHERE FollowerID = :follower AND FollowedID = :followed');
        $stmt->execute([':follower' => $followerId, ':followed' => $followedId]);
    }

    // The links a member has put on the profile (shop, Instagram ...), oldest first. Returns a list of web addresses.
    public function linksOf(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT Url FROM `UserSocialLink` WHERE UserID = :id ORDER BY SocialLinkID');
        $stmt->execute([':id' => $userId]);
        $urls = [];
        foreach ($stmt->fetchAll() as $row) {
            $urls[] = $row['Url'];
        }
        return $urls;
    }

    // Edit profile: replace ALL links of this member with the new list (an empty list removes them all).
    // First delete the old rows, then insert the new ones. The controller wraps this in a transaction.
    public function replaceLinks(int $userId, array $urls): void
    {
        $delete = $this->db->prepare('DELETE FROM `UserSocialLink` WHERE UserID = :id');
        $delete->execute([':id' => $userId]);

        $insert = $this->db->prepare('INSERT INTO `UserSocialLink` (UserID, Url) VALUES (:id, :url)');
        foreach ($urls as $url) {
            $insert->execute([':id' => $userId, ':url' => $url]);
        }
    }

    // The four privacy and notification switches of a member (1 = on, 0 = off). Shown on the My account page.
    public function settingsOf(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT IsProfilePublic, ShowFollowerCount, ShowLikes, NotificationsOn FROM `User` WHERE UserID = :id LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            // No such member: the same values a new member starts with (everything on).
            return ['IsProfilePublic' => 1, 'ShowFollowerCount' => 1, 'ShowLikes' => 1, 'NotificationsOn' => 1];
        }
        return $row;
    }

    // My account: save the four switches. Each value is 1 (on) or 0 (off).
    // The WHERE UserID = :id part makes sure only this one member's row is changed.
    public function updateSettings(int $userId, int $isPublic, int $showFollowers, int $showLikes, int $notifications): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `User`
                SET IsProfilePublic = :public, ShowFollowerCount = :followers, ShowLikes = :likes, NotificationsOn = :notifications
              WHERE UserID = :id'
        );
        $stmt->execute([
            ':public'        => $isPublic,
            ':followers'     => $showFollowers,
            ':likes'         => $showLikes,
            ':notifications' => $notifications,
            ':id'            => $userId,
        ]);
    }

    // Is this username used by SOMEONE ELSE? True or false. The member's own row does not count, so keeping your name is fine.
    // LOWER() on both sides makes it case-insensitive: "Mira" and "mira" count as the same name (same idea as at sign-up).
    public function usernameTakenByOther(string $username, int $myId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `User` WHERE LOWER(Username) = LOWER(:u) AND UserID <> :id');
        $stmt->execute([':u' => $username, ':id' => $myId]);
        $howMany = (int)$stmt->fetchColumn();
        if ($howMany > 0) {
            return true;
        }
        return false;
    }

    // Edit profile: save the first name, last name and username. Only this one member's row is changed (WHERE UserID = :id).
    public function updateNames(int $userId, string $firstName, string $lastName, string $username): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `User` SET FirstName = :first, LastName = :last, Username = :username WHERE UserID = :id'
        );
        $stmt->execute([
            ':first'    => $firstName,
            ':last'     => $lastName,
            ':username' => $username,
            ':id'       => $userId,
        ]);
    }

    // Edit profile: save the name of the profile photo file, or NULL when the photo is removed (the initials are shown again).
    // WHERE UserID = :id makes sure only this one member's row is changed.
    public function updateAvatar(int $userId, ?string $fileName): void
    {
        $stmt = $this->db->prepare('UPDATE `User` SET AvatarFile = :file WHERE UserID = :id');
        $stmt->execute([':file' => $fileName, ':id' => $userId]);
    }

    // Edit profile: save the three texts of the profile. An empty text is saved as NULL (= "not filled in").
    // The WHERE UserID = :id part makes sure only this one member's row is changed.
    public function updateProfileTexts(int $userId, ?string $tagline, ?string $about, ?string $badge): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `User` SET Tagline = :tagline, AboutText = :about, Badge = :badge WHERE UserID = :id'
        );
        $stmt->execute([
            ':tagline' => $tagline,
            ':about'   => $about,
            ':badge'   => $badge,
            ':id'      => $userId,
        ]);
    }

    // Delete a member. The database does the rest by itself (ON DELETE CASCADE): the posts, comments, likes,
    // follows, notifications, links, crafts and tokens of this member disappear together with the row.
    public function deleteUser(int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM `User` WHERE UserID = :id');
        $stmt->execute([':id' => $userId]);
    }

    // Save a new password hash (used when the hash is made stronger at login).
    public function updatePasswordHash(int $userId, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE `User` SET PasswordHash = :h WHERE UserID = :id');
        $stmt->execute([':h' => $hash, ':id' => $userId]);
    }

    // Sign-up: which of these two are already used? Returns the matching rows (0, 1 or 2) with Username and Email.
    // The collation utf8mb4_bin is case-sensitive, so "Mira" and "mira" would count as two different names.
    // LOWER() on both sides makes the username check case-insensitive. The e-mail is already lower case
    // (the controller did that), and stored e-mails are lower case too, so a plain = is enough.
    public function findTaken(string $username, string $email): array
    {
        $stmt = $this->db->prepare('SELECT Username, Email FROM `User` WHERE LOWER(Username) = LOWER(:u) OR Email = :e');
        $stmt->execute([':u' => $username, ':e' => $email]);
        return $stmt->fetchAll();
    }

    // Sign-up: INSERT a new user (not confirmed yet) and return the id the database gave it.
    // $data holds ready-to-store values: username, first_name, last_name, email, birthdate, password_hash.
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO `User` (Username, FirstName, LastName, Email, Birthdate, PasswordHash, HumanMadeConfirmedAt)
             VALUES (:u, :f, :l, :e, :b, :p, :h)'
        );
        $stmt->execute([
            ':u' => $data['username'],
            ':f' => $data['first_name'],
            ':l' => $data['last_name'],
            ':e' => $data['email'],
            ':b' => $data['birthdate'],
            ':p' => $data['password_hash'],
            ':h' => date('Y-m-d H:i:s'),
        ]);
        return (int)$this->db->lastInsertId();      // the AUTO_INCREMENT id the database just gave the new user
    }

    // The confirmation link was opened: mark the e-mail as confirmed (only if it is not already).
    public function markVerified(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE `User` SET EmailVerifiedAt = :now WHERE UserID = :id AND EmailVerifiedAt IS NULL');
        $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $userId]);
    }

    // Join step 3: keep the original acceptance date if the member already agreed.
    public function acceptGuidelines(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE `User` SET GuidelinesAcceptedAt = :now WHERE UserID = :id AND GuidelinesAcceptedAt IS NULL');
        $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $userId]);
    }

    // "Send me a new link": only accounts whose e-mail is NOT confirmed yet.
    public function findUnverifiedByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT UserID, Username FROM `User` WHERE Email = :e AND EmailVerifiedAt IS NULL LIMIT 1');
        $stmt->execute([':e' => $email]);
        return $stmt->fetch() ?: null;
    }

    // "Forgot password": does this e-mail have an account? (only the columns needed for the e-mail)
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT UserID, Username FROM `User` WHERE Email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        return $stmt->fetch() ?: null;
    }

    // The reset page needs the name and e-mail (the new password may not be the same as either).
    public function findForReset(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT UserID, Username, Email FROM `User` WHERE UserID = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    // Save a new password hash after "forgot password". If the e-mail was not confirmed yet, it is now:
    // the person just proved they own the inbox (COALESCE keeps an existing date).
    public function resetPassword(int $userId, string $hash): void
    {
        $stmt = $this->db->prepare(
            'UPDATE `User`
                SET PasswordHash = :h, EmailVerifiedAt = COALESCE(EmailVerifiedAt, :now)
              WHERE UserID = :id'
        );
        $stmt->execute([':h' => $hash, ':now' => date('Y-m-d H:i:s'), ':id' => $userId]);
    }

    // Change password (logged in): the current hash is needed to check the "current password" field.
    public function findHash(int $userId): ?string
    {
        $stmt = $this->db->prepare('SELECT PasswordHash FROM `User` WHERE UserID = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $hash = $stmt->fetchColumn();
        return $hash === false ? null : (string)$hash;
    }
}
