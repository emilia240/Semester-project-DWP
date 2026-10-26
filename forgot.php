<?php
/*
forgot.php  =  "Forgot password" step 1: ask for the link.     (FigJam flow: "Forgot password (logged out)")

  type e-mail -> does the e-mail have an account?
      yes -> make a random token with a SHORT expiry, store its hash, e-mail the reset link
      no  -> do nothing
  BOTH roads end on the same message: "If the e-mail has an account, we sent a link."

Why the same message: otherwise this form would tell strangers who has an account.
*/
require_once 'includes/init.php';

if (logged_in()) {
    redirect_to('index.php');           // already logged in: nothing to recover
}

$email = '';

if (is_post()) {
    if (!csrf_check()) {
        flash_set('error', 'The form expired. Please try again.');
        redirect_to('forgot.php');
    }

    $email = mb_strtolower(post_str('email'));

    if (validate_email($email) === '') {

        $stmt = $db->prepare('SELECT UserID, Username FROM `User` WHERE Email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Anti-spam: if a reset link was made less than 60 seconds ago, do nothing.
            // (A token lives RESET_TOKEN_HOURS, so "created" = ExpiresAt minus that.)
            $stmt = $db->prepare('SELECT COUNT(*) FROM `Token` WHERE UserID = :id AND TokenType = :t AND ExpiresAt > :limit');
            $stmt->execute([
                ':id'    => $user['UserID'],
                ':t'     => 'reset_password',
                ':limit' => date('Y-m-d H:i:s', time() + RESET_TOKEN_HOURS * 3600 - 60),
            ]);
            $tooSoon = (int)$stmt->fetchColumn() > 0;

            if (!$tooSoon) {
                // create_token() first deletes this user's older reset tokens, so only the newest link works.
                $rawToken = create_token($db, (int)$user['UserID'], 'reset_password', RESET_TOKEN_HOURS);
                send_reset_email($email, $user['Username'], $rawToken);
            }
        }
        delete_expired_tokens($db);     // a bit of housekeeping
    }

    // Same message for: unknown e-mail, sent, too soon, invalid e-mail.
    flash_set('success', 'If that e-mail has an account, we have sent a link to reset the password.');
    if (MAIL_MODE === 'log') {
        flash_set('info', 'DEV MODE: open logs/mail.log to find the link.');
    }
    redirect_to('login.php');
}

$pageTitle = 'Forgot password';
require 'includes/header.php';
?>

<h1>Forgot your password?</h1>
<p>Type the e-mail you signed up with. If it has an account, we will send a link to choose a new password.</p>

<form method="post" action="forgot.php">
    <?= csrf_field() ?>
    <label for="email">E-mail</label>
    <input id="email" type="email" name="email" value="<?= e($email) ?>" required maxlength="190" autocomplete="email">
    <button type="submit">Send me a reset link</button>
</form>

<p><a href="login.php">Back to log in</a></p>

<?php require 'includes/footer.php'; ?>
