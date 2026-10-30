<?php
/*
TokenModel  =  everything about the `Token` table: the secret links in e-mails (confirm e-mail, reset password).

How a token works (see the FigJam flows "Sign up" / "User clicks the link"):
  1) make a long random string            -> goes in the e-mail link
  2) store only its SHA-256 HASH in the DB -> a leaked database gives nobody a working link
  3) when the link is clicked: hash what came in the URL, look for that hash, check it has not expired
  4) after use: DELETE it (one use only)
sha256 (not bcrypt) is fine HERE because the token is 64 random hex characters = impossible to guess,
unlike a human password. bcrypt would make every click slower for no gain.
*/
class TokenModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Make a new token for a user and return the RAW token (for the e-mail link). Only the hash is stored.
    public function create(int $userId, string $type, int $hoursValid): string
    {
        // remove older tokens of the same type for this user, so only the newest link works
        $del = $this->db->prepare('DELETE FROM `Token` WHERE UserID = :uid AND TokenType = :type');
        $del->execute([':uid' => $userId, ':type' => $type]);

        $raw     = bin2hex(random_bytes(32));                   // what goes into the e-mail
        $hash    = hash('sha256', $raw);                         // what goes into the database
        $expires = date('Y-m-d H:i:s', time() + $hoursValid * 3600);

        $ins = $this->db->prepare(
            'INSERT INTO `Token` (UserID, TokenHash, TokenType, ExpiresAt) VALUES (:uid, :hash, :type, :exp)'
        );
        $ins->execute([':uid' => $userId, ':hash' => $hash, ':type' => $type, ':exp' => $expires]);

        return $raw;
    }

    // Finds a token of the right type by its hash. Returns the Token row (with ExpiresAt) or null.
    // The caller checks the expiry, because the flowchart shows TWO different messages:
    // "Link not valid" (not found) and "Link expired" (found, but too old).
    public function find(string $raw, string $type): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {      // wrong shape = no need to even ask the database
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT TokenID, UserID, ExpiresAt FROM `Token`
              WHERE TokenHash = :hash AND TokenType = :type
              LIMIT 1'
        );
        $stmt->execute([':hash' => hash('sha256', $raw), ':type' => $type]);
        return $stmt->fetch() ?: null;
    }

    public function isExpired(array $token): bool
    {
        return strtotime($token['ExpiresAt']) <= time();
    }

    // Delete all tokens of the given types for one user (used after a link was used).
    public function deleteForUser(int $userId, array $types): void
    {
        foreach ($types as $type) {
            $stmt = $this->db->prepare('DELETE FROM `Token` WHERE UserID = :id AND TokenType = :type');
            $stmt->execute([':id' => $userId, ':type' => $type]);
        }
    }

    // Was a token of this type made for this user in the last minute? Used to stop people from asking for e-mails
    // again and again (nobody should be able to fill someone's inbox through our form).
    public function madeInLastMinute(int $userId, string $type, int $hoursValid): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `Token` WHERE UserID = :id AND TokenType = :t AND ExpiresAt > :limit');
        $stmt->execute([
            ':id'    => $userId,
            ':t'     => $type,
            ':limit' => date('Y-m-d H:i:s', time() + $hoursValid * 3600 - 60),   // "still valid for more than (full time - 60 s)" = made less than a minute ago
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // Throw away expired tokens (called from time to time so the table does not grow forever).
    public function deleteExpired(): void
    {
        $stmt = $this->db->prepare('DELETE FROM `Token` WHERE ExpiresAt < :now');
        $stmt->execute([':now' => date('Y-m-d H:i:s')]);
    }
}
