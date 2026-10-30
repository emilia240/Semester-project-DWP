<?php
/*
reset.php  =  "Forgot password" step 2: the page behind the link in the e-mail.    (FigJam flow: "User clicks the reset link")

  reset.php?token=3f9a...64 characters...

  token not found / expired -> "Link not valid or expired"  (back to forgot.php)
  token ok                  -> form: new password twice
  passwords fine?  no  -> show the errors, same form again
                   yes -> save the new hash AND delete the token (one use only) -> "Password changed" -> Log in
*/
require_once 'includes/init.php';

// $_GET can be anything (even an array: ?token[]=x), so make sure it is a string.
$raw = $_GET['token'] ?? '';
$raw = is_string($raw) ? trim($raw) : '';

// The token is checked on EVERY request, also when the form is sent: the link may have expired while the page was open.
$token = find_token($db, $raw, 'reset_password');

if ($token === null) {
    flash_set('error', 'This link is not valid. It may have been used already. You can ask for a new one.');
    redirect_to('forgot.php');
}
if (token_is_expired($token)) {
    flash_set('error', 'This link has expired. You can ask for a new one.');
    redirect_to('forgot.php');
}

// Who is this? (We need the username and e-mail for the password check, and the e-mail for the notice.)
$stmt = $db->prepare('SELECT UserID, Username, Email FROM `User` WHERE UserID = :id LIMIT 1');
$stmt->execute([':id' => $token['UserID']]);
$user = $stmt->fetch();
if (!$user) {
    redirect_to('forgot.php');
}

$error = '';

if (is_post()) {
    $password  = post_raw('password');
    $password2 = post_raw('password2');

    if (!csrf_check()) {
        $error = 'The form expired. Please try again.';

    } elseif ($msg = validate_password($password, $user['Username'], $user['Email'])) {
        $error = $msg;                                          // not strong enough: show the reason, same form again

    } elseif ($password !== $password2) {
        $error = 'The two passwords are not the same.';

    } else {
        // Save the new password AND delete the token: both or neither (transaction).
        try {
            $db->beginTransaction();

            // COALESCE(EmailVerifiedAt, :now): somebody who just proved they can read this mailbox has confirmed the e-mail too.
            $stmt = $db->prepare(
                'UPDATE `User`
                    SET PasswordHash = :h, EmailVerifiedAt = COALESCE(EmailVerifiedAt, :now)
                  WHERE UserID = :id'
            );
            $stmt->execute([
                ':h'   => password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]),
                ':now' => date('Y-m-d H:i:s'),
                ':id'  => $user['UserID'],
            ]);

            // One use only. Also remove old confirmation links: the e-mail is confirmed now.
            $stmt = $db->prepare('DELETE FROM `Token` WHERE UserID = :id AND TokenType IN (:t1, :t2)');
            $stmt->execute([':id' => $user['UserID'], ':t1' => 'reset_password', ':t2' => 'verify_email']);

            $db->commit();
        } catch (PDOException $ex) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('reset failed: ' . $ex->getMessage());
            flash_set('error', 'Something went wrong. Please try the link again in a moment.');
            redirect_to('reset.php?token=' . $raw);
        }

        send_password_changed_email($user['Email'], $user['Username']);
        login_clear_failures();                                 // a locked-out user can try the new password straight away
        flash_set('success', 'Password changed! You can log in with the new password now.');
        redirect_to(url('login'));
    }
}

$pageTitle = 'Choose a new password';
require 'includes/header.php';
?>

<h1>Choose a new password</h1>

<?php if ($error): ?>
    <p class="msg msg-error"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" action="reset.php?token=<?= e($raw) ?>">
    <?= csrf_field() ?>

    <label for="password">New password</label>
    <input id="password" type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
    <small>8-72 characters, at least one letter and one number.</small>

    <label for="password2">Repeat new password</label>
    <input id="password2" type="password" name="password2" required maxlength="72" autocomplete="new-password">

    <button type="submit">Save new password</button>
</form>

<?php require 'includes/footer.php'; ?>
