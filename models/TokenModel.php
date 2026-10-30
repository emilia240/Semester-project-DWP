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
}
