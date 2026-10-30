<?php
/*
resend.php  =  "Send me a new link" (button in the FigJam flow).

The answer is ALWAYS the same sentence, whether the e-mail exists or not.
Otherwise this page would be a free tool for strangers to check who has an account.
*/
require_once 'includes/init.php';

if (logged_in()) {
    redirect_to('index.php');
}

$email = '';

if (is_post()) {
    if (!csrf_check()) {
        flash_set('error', 'The form expired. Please try again.');
        redirect_to('resend.php');
    }

    $email = mb_strtolower(post_str('email'));

    if (validate_email($email) === '') {

        // Only accounts that exist AND are not confirmed yet get a new link.
        $stmt = $db->prepare('SELECT UserID, Username FROM `User` WHERE Email = :e AND EmailVerifiedAt IS NULL LIMIT 1');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Anti-spam: if a link was made less than 60 seconds ago, do nothing.
            // (A token lives VERIFY_TOKEN_HOURS, so "created" = ExpiresAt minus that.)
            $stmt = $db->prepare('SELECT COUNT(*) FROM `Token` WHERE UserID = :id AND TokenType = :t AND ExpiresAt > :limit');
            $stmt->execute([
                ':id'    => $user['UserID'],
                ':t'     => 'verify_email',
                ':limit' => date('Y-m-d H:i:s', time() + VERIFY_TOKEN_HOURS * 3600 - 60),
            ]);
            $tooSoon = (int)$stmt->fetchColumn() > 0;

            if (!$tooSoon) {
                $rawToken = create_token($db, (int)$user['UserID'], 'verify_email', VERIFY_TOKEN_HOURS);
                send_verification_email($email, $user['Username'], $rawToken);
            }
        }
        delete_expired_tokens($db);     // a bit of housekeeping
    }

    // Same message for: unknown e-mail, already confirmed, sent, too soon, invalid e-mail.
    flash_set('success', 'If that e-mail has an account that is not confirmed yet, we have sent a new link.');
    if (MAIL_MODE === 'log') {
        flash_set('info', 'DEV MODE: open logs/mail.log to find the link.');
    }
    redirect_to(url('login'));
}

$pageTitle = 'New confirmation link';
require 'includes/header.php';
?>

<h1>Send me a new link</h1>
<p>Type the e-mail you signed up with and we will send a fresh confirmation link.</p>

<form method="post" action="resend.php">
    <?= csrf_field() ?>
    <label for="email">E-mail</label>
    <input id="email" type="email" name="email" value="<?= e($email) ?>" required maxlength="190" autocomplete="email">
    <button type="submit">Send me a new link</button>
</form>

<p><a href="<?= e(url('login')) ?>">Back to log in</a></p>

<?php require 'includes/footer.php'; ?>
