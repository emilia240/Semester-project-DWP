<?php
/*
AuthController  =  everything about getting IN and OUT of the site:
log in, log out, sign up, confirm the e-mail, forgot and reset password.
Each public method is one page (see the route table in index.php). The same method answers both:
  GET   the visitor opens the page      -> show the form
  POST  the visitor sent the form       -> check it, do the work, redirect (or show the form again with errors)
*/
class AuthController extends BaseController
{
    // =================================================================
    // LOG IN                                              (FigJam flow: "Log in")
    //
    //  1. CSRF ok? Not locked out by too many failures?
    //  2. Find the user by e-mail
    //  3. password_verify(typed password, hash from the database)
    //  4. Correct, but e-mail not confirmed -> "Please confirm your email"
    //  5. Correct, but banned              -> "Account suspended"
    //  6. Otherwise: start the session
    //
    // We never say WHICH part was wrong ("unknown e-mail" vs "wrong password"). Both give the
    // same sentence, so an attacker cannot use the form to find out who is registered.
    // =================================================================
    public function login(): void
    {
        $users      = new UserModel($this->db);
        $error      = '';
        $email      = '';
        $showResend = false;

        if (is_post()) {

            if (!csrf_check()) {
                $error = 'The form expired. Please try again.';

            } elseif (login_is_locked()) {
                $error = 'Too many failed attempts. Please wait a minute and try again.';

            } else {
                $email    = mb_strtolower(post_str('email'));
                $password = post_raw('password');

                // ---- 2. find the user (only the columns we need) ----
                $user = $users->findForLogin($email);          // an array, or null if there is no such e-mail

                // ---- 3. check the password ----
                // If the e-mail does not exist we still verify against a fake hash, so both cases take equally long.
                $hashToCheck = $user ? $user['PasswordHash'] : DUMMY_HASH;
                $passwordOk  = password_verify($password, $hashToCheck);

                if (!$user || !$passwordOk) {
                    login_register_failure();
                    $error = 'Wrong e-mail or password.';

                } elseif ($user['EmailVerifiedAt'] === null) {
                    // ---- 4. the flowchart's "Email verified?" diamond. Safe to tell: they proved they know the password.
                    $error      = 'Please confirm your e-mail first. We sent you a link when you signed up.';
                    $showResend = true;

                } elseif (is_banned($user)) {
                    // ---- 5. banned ----
                    $error = $user['BannedUntil'] === null
                        ? 'This account has been suspended.'
                        : 'This account is suspended until ' . date('j M Y H:i', strtotime($user['BannedUntil'])) . '.';

                } else {
                    // ---- 6. success ----
                    login_clear_failures();

                    // Bcrypt can get "stronger" over the years. If our settings changed since this hash was made, re-hash now.
                    if (password_needs_rehash($user['PasswordHash'], PASSWORD_BCRYPT, ['cost' => 10])) {
                        $users->updatePasswordHash((int)$user['UserID'], password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]));
                    }

                    // IMPORTANT: new session id at the moment of login. Otherwise an attacker who planted a known
                    // session id in the victim's browser ("session fixation") would be logged in as the victim.
                    session_regenerate_id(true);
                    unset($_SESSION['csrf_token']);                 // fresh CSRF token for the new session
                    $_SESSION['user_id'] = (int)$user['UserID'];    // THIS line is "being logged in"

                    flash_set('success', 'Welcome back, ' . $user['Username'] . '!');
                    redirect_to(url('home'));
                }
            }
        }

        $this->render('auth/login', [
            'layout'     => 'split',
            'title'      => 'Log in',
            'error'      => $error,
            'email'      => $email,
            'showResend' => $showResend,
        ]);
    }

    // =================================================================
    // SIGN UP ("Join us")                                 (FigJam flow: "Sign up")
    //
    // Steps when the form is sent:
    //   1. CSRF token ok?
    //   2. Read the fields (always as strings)
    //   3. Validate on the server -> collect error messages in $errors
    //   4. Is the username / e-mail already taken?
    //   5. Hash the password with bcrypt
    //   6. INSERT the user (NOT confirmed yet) + make a token   (one transaction)
    //   7. E-mail the confirmation link
    //   8. Redirect to the "Check your inbox" page
    // =================================================================
    public function join(): void
    {
        $users  = new UserModel($this->db);
        $tokens = new TokenModel($this->db);

        $errors = [];
        $old = ['username' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'birthdate' => '', 'human_made' => false];

        if (is_post()) {

            if (!csrf_check()) {
                $errors['form'] = 'The form expired. Please try again.';
            } else {

                $username  = post_str('username');
                $firstName = post_str('first_name');
                $lastName  = post_str('last_name');
                $email     = mb_strtolower(post_str('email'));     // e-mails are compared in lower case
                $birthdate = post_str('birthdate');
                $password  = post_raw('password');                 // passwords are not trimmed
                $password2 = post_raw('password2');
                $humanMade = isset($_POST['human_made']);

                // what we show again in the form if something is wrong (never the passwords!)
                $old = ['username' => $username, 'first_name' => $firstName, 'last_name' => $lastName,
                        'email' => $email, 'birthdate' => $birthdate, 'human_made' => $humanMade];

                // ---- 3. validate (rules live in includes/functions.php, section F) ----
                if ($msg = validate_username($username))                         { $errors['username']   = $msg; }
                if ($msg = validate_person_name($firstName, 'First name'))       { $errors['first_name'] = $msg; }
                if ($msg = validate_person_name($lastName, 'Last name'))         { $errors['last_name']  = $msg; }
                if ($msg = validate_email($email))                               { $errors['email']      = $msg; }
                if ($msg = validate_birthdate($birthdate))                       { $errors['birthdate']  = $msg; }
                if ($msg = validate_password($password, $username, $email))      { $errors['password']   = $msg; }
                elseif ($password !== $password2)                                { $errors['password2']  = 'The two passwords are not the same.'; }
                if (!$humanMade)                                                 { $errors['human_made'] = 'Please confirm that you only share work you made yourself.'; }

                // ---- 4. taken? (the UNIQUE keys in the database are the final guard, see the catch below) ----
                if (!$errors) {
                    foreach ($users->findTaken($username, $email) as $row) {
                        if (strcasecmp($row['Username'], $username) === 0) { $errors['username'] = 'That username is taken.'; }
                        if (strcasecmp($row['Email'], $email) === 0)       { $errors['email']    = 'That e-mail already has an account. Try logging in.'; }
                    }
                }

                if (!$errors) {

                    // ---- 5. bcrypt: slow on purpose, with its own random salt ----
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

                    // ---- 6. user + token in ONE transaction: both are saved, or neither ----
                    try {
                        $this->db->beginTransaction();

                        $userId = $users->create([
                            'username'      => $username,
                            'first_name'    => $firstName,
                            'last_name'     => $lastName,
                            'email'         => $email,
                            'birthdate'     => $birthdate,
                            'password_hash' => $hash,
                        ]);
                        $rawToken = $tokens->create($userId, 'verify_email', VERIFY_TOKEN_HOURS);

                        $this->db->commit();

                    } catch (PDOException $ex) {
                        if ($this->db->inTransaction()) { $this->db->rollBack(); }

                        if ($ex->getCode() === '23000') {
                            // two people signed up with the same name at the same moment: the UNIQUE key said no
                            $errors['form'] = 'That username or e-mail was just taken. Please try again.';
                        } else {
                            error_log('signup failed: ' . $ex->getMessage());      // details to the log, never to the visitor
                            $errors['form'] = 'Something went wrong on our side. Please try again later.';
                        }
                    }

                    // ---- 7. e-mail + 8. redirect ----
                    if (!$errors) {
                        $sent = send_verification_email($email, $username, $rawToken);

                        if ($sent) {
                            flash_set('success', 'Account created! We sent a confirmation link to ' . $email . '.');
                            if (MAIL_MODE === 'log') {
                                flash_set('info', 'DEV MODE: no real e-mail was sent. Open logs/mail.log and copy the link.');
                            }
                        } else {
                            flash_set('warning', 'Account created, but we could not send the e-mail. Use "Send me a new link" to try again.');
                        }
                        redirect_to(url('check-inbox'));
                    }
                }
            }
        }

        $this->render('auth/join', [
            'layout' => 'split',
            'title'  => 'Join us',
            'errors' => $errors,
            'old'    => $old,
        ]);
    }

    // "Check your inbox": the page after sign-up. Nothing to do here, only to show.
    public function checkInbox(): void
    {
        $this->render('auth/check_inbox', ['layout' => 'split', 'title' => 'Check your inbox']);
    }

    // =================================================================
    // THE LINK IN THE CONFIRMATION E-MAIL                 (FigJam flow: "User clicks the link")
    //
    //   index.php?page=verify&token=3f9a...64 characters...
    //
    //   token not found  -> "Link not valid"
    //   token expired    -> "Link expired" + a way to get a new one
    //   token ok         -> mark the user as confirmed, DELETE the token (one use only), "E-mail confirmed"
    // =================================================================
    public function verify(): void
    {
        $users  = new UserModel($this->db);
        $tokens = new TokenModel($this->db);

        $raw = $_GET['token'] ?? '';
        $raw = is_string($raw) ? trim($raw) : '';          // ?token[]=x would be an array: treat it as "no token"

        $token = $tokens->find($raw, 'verify_email');

        if ($token === null) {
            flash_set('error', 'This link is not valid. It may have been used already.');
            redirect_to(url('resend'));
        }
        if ($tokens->isExpired($token)) {
            flash_set('error', 'This link has expired. Request a new one below.');
            redirect_to(url('resend'));
        }

        try {
            $this->db->beginTransaction();
            $users->markVerified((int)$token['UserID']);
            $tokens->deleteForUser((int)$token['UserID'], ['verify_email']);       // one use only
            $this->db->commit();
        } catch (PDOException $ex) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            error_log('verify failed: ' . $ex->getMessage());
            flash_set('error', 'Something went wrong. Please try the link again in a moment.');
            redirect_to(url('resend'));
        }

        flash_set('success', 'E-mail confirmed! You can log in now.');
        redirect_to(url('login'));
    }

    // =================================================================
    // "SEND ME A NEW LINK"                                (button in the FigJam flow)
    //
    // The answer is ALWAYS the same sentence, whether the e-mail exists or not.
    // Otherwise this page would be a free tool for strangers to check who has an account.
    // =================================================================
    public function resend(): void
    {
        $users  = new UserModel($this->db);
        $tokens = new TokenModel($this->db);
        $email  = '';

        if (is_post()) {
            if (!csrf_check()) {
                flash_set('error', 'The form expired. Please try again.');
                redirect_to(url('resend'));
            }

            $email = mb_strtolower(post_str('email'));

            if (validate_email($email) === '') {
                $user = $users->findUnverifiedByEmail($email);

                if ($user && !$tokens->madeInLastMinute((int)$user['UserID'], 'verify_email', VERIFY_TOKEN_HOURS)) {
                    $rawToken = $tokens->create((int)$user['UserID'], 'verify_email', VERIFY_TOKEN_HOURS);
                    send_verification_email($email, $user['Username'], $rawToken);
                }
                $tokens->deleteExpired();       // a bit of housekeeping
            }

            flash_set('success', 'If that e-mail has an account that is not confirmed yet, we have sent a new link.');
            if (MAIL_MODE === 'log') {
                flash_set('info', 'DEV MODE: open logs/mail.log to find the link.');
            }
            redirect_to(url('login'));
        }

        $this->render('auth/resend', ['layout' => 'split', 'title' => 'New confirmation link', 'email' => $email]);
    }

    // =================================================================
    // FORGOT PASSWORD, step 1: ask for the link          (FigJam flow: "Forgot password (logged out)")
    //
    //   type e-mail -> does the e-mail have an account?
    //       yes -> make a random token with a SHORT expiry, store its hash, e-mail the reset link
    //       no  -> do nothing
    //   BOTH roads end on the same message: "If the e-mail has an account, we sent a link."
    //
    // Why the same message: otherwise this form would tell strangers who has an account.
    // =================================================================
    public function forgot(): void
    {
        $users  = new UserModel($this->db);
        $tokens = new TokenModel($this->db);
        $email  = '';

        if (is_post()) {
            if (!csrf_check()) {
                flash_set('error', 'The form expired. Please try again.');
                redirect_to(url('forgot'));
            }

            $email = mb_strtolower(post_str('email'));

            if (validate_email($email) === '') {
                $user = $users->findByEmail($email);

                // Not more than one reset e-mail per minute per account (nobody can fill an inbox through our form).
                if ($user && !$tokens->madeInLastMinute((int)$user['UserID'], 'reset_password', RESET_TOKEN_HOURS)) {
                    $rawToken = $tokens->create((int)$user['UserID'], 'reset_password', RESET_TOKEN_HOURS);
                    send_reset_email($email, $user['Username'], $rawToken);
                }
                $tokens->deleteExpired();       // a bit of housekeeping
            }

            flash_set('success', 'If that e-mail has an account, we have sent a link to reset the password.');
            if (MAIL_MODE === 'log') {
                flash_set('info', 'DEV MODE: open logs/mail.log to find the link.');
            }
            redirect_to(url('login'));
        }

        $this->render('auth/forgot', ['layout' => 'split', 'title' => 'Forgot password', 'email' => $email]);
    }

    // =================================================================
    // FORGOT PASSWORD, step 2: the page behind the link in the e-mail      (FigJam flow: "User clicks the reset link")
    //
    //   index.php?page=reset&token=3f9a...64 characters...
    //
    //   token not found / expired -> "Link not valid or expired"  (back to the forgot page)
    //   token ok                  -> form: new password twice
    //   passwords fine?  no  -> show the error, same form again
    //                    yes -> save the new hash AND delete the token (one use only) -> "Password changed" -> Log in
    // =================================================================
    public function reset(): void
    {
        $users  = new UserModel($this->db);
        $tokens = new TokenModel($this->db);

        $raw = $_GET['token'] ?? '';
        $raw = is_string($raw) ? trim($raw) : '';

        $token = $tokens->find($raw, 'reset_password');

        if ($token === null) {
            flash_set('error', 'This link is not valid. It may have been used already. You can ask for a new one.');
            redirect_to(url('forgot'));
        }
        if ($tokens->isExpired($token)) {
            flash_set('error', 'This link has expired. You can ask for a new one.');
            redirect_to(url('forgot'));
        }

        $user = $users->findForReset((int)$token['UserID']);
        if (!$user) {
            redirect_to(url('forgot'));
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
                try {
                    $this->db->beginTransaction();
                    $users->resetPassword((int)$user['UserID'], password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]));
                    // the link is used up. Also delete a confirmation link that may still be lying around.
                    $tokens->deleteForUser((int)$user['UserID'], ['reset_password', 'verify_email']);
                    $this->db->commit();
                } catch (PDOException $ex) {
                    if ($this->db->inTransaction()) { $this->db->rollBack(); }
                    error_log('reset failed: ' . $ex->getMessage());
                    flash_set('error', 'Something went wrong. Please try the link again in a moment.');
                    redirect_to(url('reset', ['token' => $raw]));
                }

                send_password_changed_email($user['Email'], $user['Username']);
                login_clear_failures();                                 // a locked-out user can try the new password straight away
                flash_set('success', 'Password changed! You can log in with the new password now.');
                redirect_to(url('login'));
            }
        }

        $this->render('auth/reset', ['layout' => 'split', 'title' => 'Choose a new password', 'error' => $error, 'rawToken' => $raw]);
    }

    // =================================================================
    // LOG OUT                                              (FigJam flow: "Log out")
    //
    // Only accepts POST with a valid CSRF token (the "Log out" button in the menu is a tiny form).
    // If it accepted a plain link, any website could log you out just by showing <img src="...page=logout">.
    // =================================================================
    public function logout(): void
    {
        if (!is_post()) {
            redirect_to(url('home'));
        }
        if (!csrf_check()) {
            flash_set('error', 'The form expired. Please try again.');
            redirect_to(url('home'));
        }

        destroy_session();      // empty $_SESSION + delete the cookie + destroy (see includes/session.php)

        flash_set('success', 'You are logged out. See you soon!');
        redirect_to(url('login'));
    }
}
