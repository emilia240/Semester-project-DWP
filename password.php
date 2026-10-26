<?php
/*
password.php  =  change the password while LOGGED IN.     (FigJam flow: "Change password (logged in)")

  Settings -> Password: current password, new password, repeat new password
  current password correct?  no  -> "Current password is wrong"
  new passwords match and are strong enough?  no -> show the errors
  yes -> save the new hash

The current password is asked on purpose: it stops someone who sits down at an unlocked
computer from changing the password. No e-mail link is involved here (that is "forgot password").
*/
require_once 'includes/init.php';

$user = confirm_logged_in();

$errors = [];

if (is_post()) {
    $current   = post_raw('current_password');
    $password  = post_raw('password');
    $password2 = post_raw('password2');

    if (!csrf_check()) {
        $errors['form'] = 'The form expired. Please try again.';

    } elseif (login_is_locked()) {
        // Someone at an unlocked computer could try to GUESS the current password, so wrong guesses count like at login.
        $errors['form'] = 'Too many failed attempts. Please wait a minute and try again.';

    } else {
        // current_user() does not carry the hash, so fetch it here.
        $stmt = $db->prepare('SELECT PasswordHash FROM `User` WHERE UserID = :id LIMIT 1');
        $stmt->execute([':id' => $user['UserID']]);
        $hash = $stmt->fetchColumn();

        if ($hash === false || !password_verify($current, $hash)) {
            login_register_failure();
            $errors['current'] = 'Current password is wrong.';

        } elseif ($msg = validate_password($password, $user['Username'], $user['Email'])) {
            $errors['password'] = $msg;

        } elseif ($password !== $password2) {
            $errors['password2'] = 'The two new passwords are not the same.';

        } elseif ($password === $current) {
            $errors['password'] = 'The new password must be different from the current one.';

        } else {
            // ---- save ----
            $db->beginTransaction();
            $stmt = $db->prepare('UPDATE `User` SET PasswordHash = :h WHERE UserID = :id');
            $stmt->execute([':h' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]), ':id' => $user['UserID']]);

            // A reset link that is still out there must not work any more.
            $stmt = $db->prepare('DELETE FROM `Token` WHERE UserID = :id AND TokenType = :t');
            $stmt->execute([':id' => $user['UserID'], ':t' => 'reset_password']);
            $db->commit();

            // Sensitive change = new session id (same idea as at login).
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            login_clear_failures();

            send_password_changed_email($user['Email'], $user['Username']);
            flash_set('success', 'Password changed.');
            redirect_to('password.php');
        }
    }
}

$pageTitle = 'Change password';
require 'includes/header.php';
?>

<h1>Change password</h1>

<?php if (isset($errors['form'])): ?><p class="msg msg-error"><?= e($errors['form']) ?></p><?php endif; ?>

<form method="post" action="password.php">
    <?= csrf_field() ?>

    <label for="current_password">Current password</label>
    <input id="current_password" type="password" name="current_password" required maxlength="72" autocomplete="current-password">
    <?php if (isset($errors['current'])): ?><p class="err"><?= e($errors['current']) ?></p><?php endif; ?>

    <label for="password">New password</label>
    <input id="password" type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
    <small>8-72 characters, at least one letter and one number.</small>
    <?php if (isset($errors['password'])): ?><p class="err"><?= e($errors['password']) ?></p><?php endif; ?>

    <label for="password2">Repeat new password</label>
    <input id="password2" type="password" name="password2" required maxlength="72" autocomplete="new-password">
    <?php if (isset($errors['password2'])): ?><p class="err"><?= e($errors['password2']) ?></p><?php endif; ?>

    <button type="submit">Change password</button>
</form>

<?php require 'includes/footer.php'; ?>
