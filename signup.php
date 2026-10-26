<?php
/*
signup.php  =  create a new account.        (FigJam flow: "Sign up")

Steps when the form is sent:
  1. Read the fields (always as strings)
  2. Validate on the server -> collect error messages in $errors
  3. Is the username / e-mail already taken?
  4. Hash the password with bcrypt
  5. INSERT the user (NOT verified yet) + make a token   (one transaction)
  6. E-mail the confirmation link
  7. Redirect to the login page with "Check your inbox"
*/
require_once 'includes/init.php';

if (logged_in()) {
    redirect_to('index.php');           // already logged in? nothing to do here
}

$errors = [];
// $old = what the user typed, so the form is not empty after an error. (Passwords are never sent back.)
$old = ['username' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'birthdate' => '', 'human_made' => false];

if (is_post()) {

    // ---- 1. read the fields ----
    $username  = post_str('username');
    $firstName = post_str('first_name');
    $lastName  = post_str('last_name');
    $email     = mb_strtolower(post_str('email'));     // e-mails are compared in lower case
    $birthdate = post_str('birthdate');
    $password  = post_raw('password');
    $password2 = post_raw('password2');
    $humanMade = isset($_POST['human_made']);

    $old = ['username' => $username, 'first_name' => $firstName, 'last_name' => $lastName,
            'email' => $email, 'birthdate' => $birthdate, 'human_made' => $humanMade];

    // ---- 2. validate (each helper returns '' when fine, or an error text) ----
    if ($msg = validate_username($username))                         { $errors['username']   = $msg; }
    if ($msg = validate_person_name($firstName, 'First name'))       { $errors['first_name'] = $msg; }
    if ($msg = validate_person_name($lastName, 'Last name'))         { $errors['last_name']  = $msg; }
    if ($msg = validate_email($email))                               { $errors['email']      = $msg; }
    if ($msg = validate_birthdate($birthdate))                       { $errors['birthdate']  = $msg; }
    if ($msg = validate_password($password, $username, $email))      { $errors['password']   = $msg; }
    elseif ($password !== $password2)                                { $errors['password2']  = 'The two passwords are not the same.'; }
    if (!$humanMade)                                                 { $errors['human_made'] = 'Please confirm that you only share work you made yourself.'; }

    // ---- 3. already taken? (only worth asking the database if the rest is fine) ----
    if (!$errors) {
        $stmt = $db->prepare('SELECT Username, Email FROM `User` WHERE Username = :u OR Email = :e');
        $stmt->execute([':u' => $username, ':e' => $email]);
        foreach ($stmt->fetchAll() as $row) {
            if (strcasecmp($row['Username'], $username) === 0) { $errors['username'] = 'That username is taken.'; }
            if (strcasecmp($row['Email'], $email) === 0)       { $errors['email']    = 'That e-mail already has an account. Try logging in.'; }
        }
    }

    // ---- 4, 5, 6, 7: save ----
    if (!$errors) {

        // password_hash makes a salted bcrypt hash (60 characters) from the password.
        // cost 10 = 2^10 rounds: slow enough to hurt a password-cracker, fast enough for a login.
        // Never md5/sha1: they are built to be FAST, which is exactly what a cracker wants.
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

        try {
            // A transaction = "all or nothing": if making the token fails, the user is NOT saved either.
            $db->beginTransaction();

            // Named placeholders (:u, :f ...) = prepared statement. The values travel SEPARATELY from the SQL,
            // so a username like  x'; DROP TABLE User; --  is just text and can never become SQL (SQL injection).
            $stmt = $db->prepare(
                'INSERT INTO `User` (Username, FirstName, LastName, Email, Birthdate, PasswordHash, HumanMadeConfirmedAt)
                 VALUES (:u, :f, :l, :e, :b, :p, :h)'
            );
            $stmt->execute([
                ':u' => $username,
                ':f' => $firstName,
                ':l' => $lastName,
                ':e' => $email,
                ':b' => $birthdate,
                ':p' => $hash,
                ':h' => date('Y-m-d H:i:s'),
            ]);
            $userId = (int)$db->lastInsertId();     // the AUTO_INCREMENT id the database just gave the new user

            $rawToken = create_token($db, $userId, 'verify_email', VERIFY_TOKEN_HOURS);

            $db->commit();

        } catch (PDOException $ex) {
            if ($db->inTransaction()) { $db->rollBack(); }

            // 23000 = "integrity constraint violation" = someone took the username/e-mail in the milliseconds
            // between step 3 and now. The UNIQUE keys in the database are the real guarantee.
            if ($ex->getCode() === '23000') {
                $errors['form'] = 'That username or e-mail was just taken. Please try again.';
            } else {
                error_log('signup failed: ' . $ex->getMessage());
                $errors['form'] = 'Something went wrong on our side. Please try again later.';
            }
        }

        // ---- 6. e-mail (AFTER commit: the account exists even if the mail server is down) ----
        if (!$errors) {
            $sent = send_verification_email($email, $username, $rawToken);

            if ($sent) {
                flash_set('success', 'Account created! We sent a confirmation link to ' . $email . '. Open it to activate your account.');
                if (MAIL_MODE === 'log') {
                    flash_set('info', 'DEV MODE: no real e-mail was sent. Open logs/mail.log and copy the link.');
                }
            } else {
                flash_set('warning', 'Account created, but we could not send the e-mail. Use "Send me a new link" on the login page to try again.');
            }
            redirect_to('login.php');
        }
    }
}

$pageTitle = 'Sign up';
require 'includes/header.php';
?>

<h1>Create your account</h1>

<?php if (isset($errors['form'])): ?><p class="msg msg-error"><?= e($errors['form']) ?></p><?php endif; ?>

<!-- novalidate is NOT used: the browser checks required/type first (friendly), PHP checks again (safe). -->
<form method="post" action="signup.php">
    <label for="username">Username</label>
    <input id="username" name="username" value="<?= e($old['username']) ?>" required minlength="3" maxlength="50" autocomplete="username">
    <?php if (isset($errors['username'])): ?><p class="err"><?= e($errors['username']) ?></p><?php endif; ?>

    <label for="first_name">First name</label>
    <input id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" required maxlength="110" autocomplete="given-name">
    <?php if (isset($errors['first_name'])): ?><p class="err"><?= e($errors['first_name']) ?></p><?php endif; ?>

    <label for="last_name">Last name</label>
    <input id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" required maxlength="110" autocomplete="family-name">
    <?php if (isset($errors['last_name'])): ?><p class="err"><?= e($errors['last_name']) ?></p><?php endif; ?>

    <label for="email">E-mail</label>
    <input id="email" type="email" name="email" value="<?= e($old['email']) ?>" required maxlength="190" autocomplete="email">
    <?php if (isset($errors['email'])): ?><p class="err"><?= e($errors['email']) ?></p><?php endif; ?>

    <label for="birthdate">Birthdate</label>
    <input id="birthdate" type="date" name="birthdate" value="<?= e($old['birthdate']) ?>" required autocomplete="bday">
    <?php if (isset($errors['birthdate'])): ?><p class="err"><?= e($errors['birthdate']) ?></p><?php endif; ?>

    <label for="password">Password</label>
    <input id="password" type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
    <small>8-72 characters, with at least one letter and one number.</small>
    <?php if (isset($errors['password'])): ?><p class="err"><?= e($errors['password']) ?></p><?php endif; ?>

    <label for="password2">Repeat password</label>
    <input id="password2" type="password" name="password2" required maxlength="72" autocomplete="new-password">
    <?php if (isset($errors['password2'])): ?><p class="err"><?= e($errors['password2']) ?></p><?php endif; ?>

    <label class="check">
        <input type="checkbox" name="human_made" value="1" <?= $old['human_made'] ? 'checked' : '' ?>>
        I only share work I made myself, by hand. No AI-generated pictures.
    </label>
    <?php if (isset($errors['human_made'])): ?><p class="err"><?= e($errors['human_made']) ?></p><?php endif; ?>

    <button type="submit">Create account</button>
</form>

<p>Already have an account? <a href="login.php">Log in</a></p>

<?php require 'includes/footer.php'; ?>
