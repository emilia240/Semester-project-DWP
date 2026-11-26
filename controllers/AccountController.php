<?php
/*
AccountController  =  pages for a logged-in member (index.php already checked that, see the 'member' routes).

  members   Settings > Account: the details we have about you, the Change password form, the privacy and notification switches,
            Log out and Delete account
  save-settings   saves the four switches (POST only)
  password  saves a new password while logged in      (POST only, the form is on the Account page)
  delete-account  deletes my own account for good     (POST only, needs my password)
*/
class AccountController extends BaseController
{
    public function index(): void
    {
        // No errors to show when the page is just opened.
        $this->showAccount([]);
    }

    // Shows the Account page. $errors (field name => message) is only filled when the Change password form was wrong.
    private function showAccount(array $errors): void
    {
        // The birthdate is only shown on this page, so it is loaded here and not on every page of the site.
        $users     = new UserModel($this->db);
        $birthdate = $users->birthdateOf((int)$this->user['UserID']);

        // The four switches (public profile, show followers, show likes, notifications) with their saved values.
        $settings = $users->settingsOf((int)$this->user['UserID']);

        $this->render('account/index', ['title' => 'Account settings', 'birthdate' => $birthdate, 'settings' => $settings, 'errors' => $errors]);
    }

    // =================================================================
    //   My account -> "Privacy and notifications": four switches (POST only)
    //   A switch that is on sends a value with the form. A switch that is off sends NOTHING.
    //   So: sent = on (1), not sent = off (0).
    //   It always saves for the member in the SESSION (the login), never for an id from the form.
    // =================================================================
    public function saveSettings(): void
    {
        // Step 1: this address is only for the form. Opening it by typing it in the address bar goes back to My account.
        if (!is_post()) {
            redirect_to(url('members'));
        }

        // Step 2: is the form really from our own page? (CSRF token)
        if (!csrf_check()) {
            flash_set('error', 'The form expired. Please try again.');
            redirect_to(url('members'));
        }

        // Step 3: read the four switches. isset() asks: was this field sent at all? (Off switches are not sent.)
        $isPublic      = 0;
        $showFollowers = 0;
        $showLikes     = 0;
        $notifications = 0;
        if (isset($_POST['is_public'])) {
            $isPublic = 1;
        }
        if (isset($_POST['show_followers'])) {
            $showFollowers = 1;
        }
        if (isset($_POST['show_likes'])) {
            $showLikes = 1;
        }
        if (isset($_POST['notifications'])) {
            $notifications = 1;
        }

        // Step 4: save for the logged-in member only, then go back with a message (Post, Redirect, Get).
        $users = new UserModel($this->db);
        $users->updateSettings((int)$this->user['UserID'], $isPublic, $showFollowers, $showLikes, $notifications);

        flash_set('success', 'Settings saved.');
        redirect_to(url('members'));
    }

    // =================================================================
    //   Settings -> Password: current password, new password, repeat new password
    //   current password correct?                   no  -> "Current password is wrong"
    //   new passwords match and are strong enough?  no  -> show the errors
    //   yes -> save the new hash
    //
    // The current password is asked on purpose: it stops someone who sits down at an unlocked computer
    // from changing the password. No e-mail link is involved here (that is "forgot password").
    // =================================================================
    public function password(): void
    {
        // The form is on the Account page now. Opening this address by hand just goes there.
        if (!is_post()) {
            redirect_to(url('members'));
        }

        $users  = new UserModel($this->db);
        $tokens = new TokenModel($this->db);
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
                $hash = $users->findHash((int)$this->user['UserID']);

                if ($hash === null || !password_verify($current, $hash)) {
                    login_register_failure();
                    $errors['current'] = 'Current password is wrong.';

                } elseif ($msg = validate_password($password, $this->user['Username'], $this->user['Email'])) {
                    $errors['password'] = $msg;

                } elseif ($password !== $password2) {
                    $errors['password2'] = 'The two new passwords are not the same.';

                } elseif ($password === $current) {
                    $errors['password'] = 'The new password must be different from the current one.';

                } else {
                    // ---- save ----
                    $this->db->beginTransaction();
                    $users->updatePasswordHash((int)$this->user['UserID'], password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]));
                    // A reset link that is still out there must not work any more.
                    $tokens->deleteForUser((int)$this->user['UserID'], ['reset_password']);
                    $this->db->commit();

                    // Sensitive change = new session id (same idea as at login).
                    session_regenerate_id(true);
                    unset($_SESSION['csrf_token']);
                    login_clear_failures();

                    send_password_changed_email($this->user['Email'], $this->user['Username']);
                    flash_set('success', 'Password changed.');
                    redirect_to(url('members'));
                }
            }
        }

        // Something was wrong: show the Account page again, now with the messages under the fields.
        $this->showAccount($errors);
    }

    // =================================================================
    //   My account -> "Delete account"   (POST only)
    //   It deletes MY OWN account for good: the id is the logged-in member's (session), never from the form.
    //   The password is asked again, so nobody at an unlocked computer can do it.
    // =================================================================
    public function deleteAccount(): void
    {
        $myId = (int)$this->user['UserID'];

        // Step 1: this address is only for the form. Typing it in the address bar goes back to the Account page.
        if (!is_post()) {
            redirect_to(url('members'));
        }

        // Step 2: is the form really from our own page? (CSRF token)
        if (!csrf_check()) {
            flash_set('error', 'The form expired. Please try again.');
            redirect_to(url('members'));
        }

        // Step 3: an admin account cannot be deleted here (the site would have no admin left).
        if ($this->user['RoleName'] === 'admin') {
            flash_set('error', 'An admin account cannot be deleted here.');
            redirect_to(url('members'));
        }

        // Step 4: wrong passwords count, like at login and at Change password. Too many in a row = wait a minute.
        if (login_is_locked()) {
            flash_set('error', 'Too many failed attempts. Please wait a minute and try again.');
            redirect_to(url('members'));
        }

        // Step 5: is the password right? password_verify() compares what was typed with the saved hash.
        $users    = new UserModel($this->db);
        $password = post_raw('password');
        $hash     = $users->findHash($myId);
        if ($hash === null || !password_verify($password, $hash)) {
            login_register_failure();
            flash_set('error', 'The password is wrong. Nothing was deleted.');
            redirect_to(url('members'));
        }

        // Step 6: the names of my picture files. The database rows disappear with the member, the files on the disk do not.
        $posts = new PostModel($this->db);
        $files = $posts->imageFilesOfUser($myId);

        // Step 7: delete the member. The database removes the rest by itself (ON DELETE CASCADE).
        $users->deleteUser($myId);

        // Step 8: now delete the picture files from the disk.
        $uploader = new ImageUploader(ROOT_PATH . '/uploads');
        $uploader->deleteMany($files);

        // Step 9: log out (the account does not exist any more) and say goodbye on the front page.
        destroy_session();
        flash_set('success', 'Your account was deleted. Goodbye.');
        redirect_to(url('home'));
    }
}
