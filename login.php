<?php
/*
login.php  =  log in with e-mail + password.        (FigJam flow: "Log in")

  1. Find the user by e-mail
  2. password_verify(typed password, hash from the database)
  3. Correct, but e-mail not confirmed -> "Please confirm your email"
  4. Otherwise: start the session

We never say WHICH part was wrong ("unknown e-mail" vs "wrong password"). Both give the
same sentence, so an attacker cannot use the form to find out who is registered.
*/
require_once 'includes/init.php';

if (logged_in()) {
    redirect_to('index.php');
}

$error    = '';
$email    = '';
$showResend = false;

if (is_post()) {

    $email    = mb_strtolower(post_str('email'));
    $password = post_raw('password');

    // ---- 1. find the user (only the columns we need) ----
    $stmt = $db->prepare(
        'SELECT UserID, Username, PasswordHash, EmailVerifiedAt
           FROM `User` WHERE Email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();          // the row as an array, or false if no such e-mail

    // ---- 2. check the password ----
    if (!$user || !password_verify($password, $user['PasswordHash'])) {
        $error = 'Wrong e-mail or password.';

    } elseif ($user['EmailVerifiedAt'] === null) {
        // ---- 3. the flowchart's "Email verified?" diamond. Safe to tell: they proved they know the password.
        $error      = 'Please confirm your e-mail first. We sent you a link when you signed up.';
        $showResend = true;

    } else {
        // ---- 4. success ----
        // IMPORTANT: new session id at the moment of login. Otherwise an attacker who planted a known
        // session id in the victim's browser ("session fixation") would be logged in as the victim.
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['UserID'];    // THIS line is "being logged in"

        flash_set('success', 'Welcome back, ' . $user['Username'] . '!');
        redirect_to('index.php');
    }
}

$pageTitle = 'Log in';
require 'includes/header.php';
?>

<h1>Log in</h1>

<?php if ($error): ?>
    <p class="msg msg-error"><?= e($error) ?>
        <?php if ($showResend): ?><a href="resend.php">Send me a new link</a><?php endif; ?>
    </p>
<?php endif; ?>

<form method="post" action="login.php">
    <label for="email">E-mail</label>
    <input id="email" type="email" name="email" value="<?= e($email) ?>" required autocomplete="email">

    <label for="password">Password</label>
    <input id="password" type="password" name="password" required autocomplete="current-password">

    <button type="submit">Log in</button>
</form>

<p>New here? <a href="signup.php">Create an account</a></p>
<p><small>Did not get the confirmation e-mail? <a href="resend.php">Send me a new link</a>.</small></p>
<p><small>Forgot your password? (coming next)</small></p>

<?php require 'includes/footer.php'; ?>
