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
                    u.EmailVerifiedAt, u.IsBanned, u.BannedUntil, u.Theme
               FROM `User` u
               JOIN `Role` r ON r.RoleID = u.RoleID
              WHERE u.UserID = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    // Save a new password hash (used when the hash is made stronger at login).
    public function updatePasswordHash(int $userId, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE `User` SET PasswordHash = :h WHERE UserID = :id');
        $stmt->execute([':h' => $hash, ':id' => $userId]);
    }

    // Sign-up: which of these two are already used? Returns the matching rows (0, 1 or 2) with Username and Email.
    public function findTaken(string $username, string $email): array
    {
        $stmt = $this->db->prepare('SELECT Username, Email FROM `User` WHERE Username = :u OR Email = :e');
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

    // "Send me a new link": only accounts whose e-mail is NOT confirmed yet.
    public function findUnverifiedByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT UserID, Username FROM `User` WHERE Email = :e AND EmailVerifiedAt IS NULL LIMIT 1');
        $stmt->execute([':e' => $email]);
        return $stmt->fetch() ?: null;
    }
}
