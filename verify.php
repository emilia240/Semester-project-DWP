<?php
/*
verify.php  =  the page behind the link in the confirmation e-mail.      (FigJam flow: "User clicks the link")

  verify.php?token=3f9a...64 characters...

  token not found      -> "Link not valid"
  token expired        -> "Link expired" + button to get a new one
  token ok             -> mark the user as verified, DELETE the token, "E-mail confirmed"
*/
require_once 'includes/init.php';

// $_GET values can be anything (even an array: ?token[]=x), so check it is a string.
$raw = $_GET['token'] ?? '';
$raw = is_string($raw) ? trim($raw) : '';

$token = find_token($db, $raw, 'verify_email');

if ($token === null) {
    flash_set('error', 'This link is not valid. It may have been used already.');
    redirect_to('resend.php');
}

if (token_is_expired($token)) {
    flash_set('error', 'This link has expired. Request a new one below.');
    redirect_to('resend.php');
}

// Token is fine -> confirm the account. Transaction: both things happen, or neither.
try {
    $db->beginTransaction();

    $stmt = $db->prepare('UPDATE `User` SET EmailVerifiedAt = :now WHERE UserID = :id AND EmailVerifiedAt IS NULL');
    $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $token['UserID']]);

    // One use only: delete every confirmation token of this user (WHERE! - never forget it on DELETE)
    $stmt = $db->prepare('DELETE FROM `Token` WHERE UserID = :id AND TokenType = :type');
    $stmt->execute([':id' => $token['UserID'], ':type' => 'verify_email']);

    $db->commit();
} catch (PDOException $ex) {
    if ($db->inTransaction()) { $db->rollBack(); }
    error_log('verify failed: ' . $ex->getMessage());
    flash_set('error', 'Something went wrong. Please try the link again in a moment.');
    redirect_to('resend.php');
}

flash_set('success', 'E-mail confirmed! You can log in now.');
redirect_to('login.php');
