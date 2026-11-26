<?php
/* Settings > Account. Needs: $user (the logged-in member, from index.php), $birthdate (like 1994-02-11), $settings (the four switches)
   and $errors (field name => message, only filled when the Change password form was wrong).
   This is the "simple user display" from the assignment: name, e-mail, birthdate, role. Only the owner can open this page.
   It sits inside the Settings layout (the sidebar): see views/partials/settings_open.php.
   Everything from the database goes through e(). */

// The birthdate as a readable date, for example 11 Feb 1994.
$birthdateText = '';
if ($birthdate !== '') {
    $birthdateText = date('j M Y', strtotime($birthdate));
}
partial('settings_open', ['active' => 'account']);
?>
    <div class="tbh-card overflow-x-auto">
        <h2 class="p-6 pb-2 text-2xl font-semibold">Your details</h2>
        <table class="table">
            <tbody>
                <tr><th scope="row">Name</th><td><?= e($user['FirstName'] . ' ' . $user['LastName']) ?></td></tr>
                <tr><th scope="row">Username</th><td><?= e($user['Username']) ?></td></tr>
                <tr><th scope="row">E-mail</th><td><?= e($user['Email']) ?></td></tr>
                <tr><th scope="row">Birthdate</th><td><?= e($birthdateText) ?></td></tr>
                <tr><th scope="row">Role</th><td><?= e($user['RoleName']) ?></td></tr>
                <tr><th scope="row">E-mail confirmed</th><td><?= e($user['EmailVerifiedAt']) ?></td></tr>
            </tbody>
        </table>
    </div>

    <?php // Change password. The form is sent to AccountController::password(); the #change-password at the end of the address brings you back to this card if something was wrong. ?>
    <div class="tbh-card p-6" id="change-password">
        <h2 class="text-2xl font-semibold">Change password</h2>
        <p class="mt-1 opacity-80">8-72 characters, at least one letter and one number.</p>

        <?php if (isset($errors['form'])): ?>
            <div class="alert alert-error mt-4" role="alert"><span><?= e($errors['form']) ?></span></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('password') . '#change-password') ?>" class="mt-6 space-y-4" data-validate>
            <?= csrf_field() ?>
            <label class="block">
                <span class="mb-1 block font-bold">Current password</span>
                <input type="password" class="input input-bordered w-full <?= field_class($errors, 'current') ?>" name="current_password" required maxlength="72" autocomplete="current-password"<?= field_attrs($errors, 'current') ?>>
                <?= field_error($errors, 'current') ?>
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block font-bold">New password</span>
                    <span class="input input-bordered flex w-full items-center gap-2 <?= field_class($errors, 'password') ?>">
                        <input type="password" class="grow" name="password" required minlength="8" maxlength="72" autocomplete="new-password" data-pw<?= field_attrs($errors, 'password') ?>>
                        <button type="button" class="btn btn-ghost btn-xs btn-circle" data-pw-toggle aria-label="Show password"><?= icon('eye', 18) ?></button>
                    </span>
                    <?= field_error($errors, 'password') ?>
                </label>
                <label class="block">
                    <span class="mb-1 block font-bold">Repeat it</span>
                    <input type="password" class="input input-bordered w-full <?= field_class($errors, 'password2') ?>" name="password2" required maxlength="72" autocomplete="new-password"<?= field_attrs($errors, 'password2') ?>>
                    <?= field_error($errors, 'password2') ?>
                </label>
            </div>
            <button type="submit" class="btn rounded-full">Change password</button>
        </form>
    </div>

    <?php
    // The four switches. 'name' is the name of the checkbox in the form, 'column' is the column in the User table.
    $switches = [
        ['name' => 'is_public',      'column' => 'IsProfilePublic',   'title' => 'Public profile',          'text' => 'When this is off, only you and the admins can open your profile page. Your posts still show in the feed and on Explore.'],
        ['name' => 'show_likes',     'column' => 'ShowLikes',         'title' => 'Show likes on my posts',  'text' => 'When this is off, nobody sees the like count on your work.'],
        ['name' => 'show_followers', 'column' => 'ShowFollowerCount', 'title' => 'Show my followers',       'text' => 'Hide the follower numbers on your profile if you prefer.'],
        ['name' => 'notifications',  'column' => 'NotificationsOn',   'title' => 'Notifications',           'text' => 'Comments, replies and new followers. Never likes.'],
    ];
    ?>
    <form method="post" action="<?= e(url('save-settings')) ?>" class="tbh-card space-y-4 p-6">
        <?= csrf_field() ?>
        <h2 class="text-2xl font-semibold">Privacy and notifications</h2>
        <?php foreach ($switches as $switch): ?>
            <?php
            // A switch is on (checked) when the saved value is 1.
            $checked = '';
            if ((int)$settings[$switch['column']] === 1) {
                $checked = ' checked';
            }
            ?>
            <label class="flex cursor-pointer items-center justify-between gap-4">
                <span>
                    <span class="block font-bold"><?= e($switch['title']) ?></span>
                    <span class="text-sm opacity-80"><?= e($switch['text']) ?></span>
                </span>
                <input type="checkbox" class="toggle toggle-primary" name="<?= e($switch['name']) ?>" value="1"<?= $checked ?>>
            </label>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary rounded-full">Save</button>
    </form>

    <?php // Log out: a small POST form with a CSRF token (the same as the one in the avatar menu). ?>
    <div class="tbh-card flex flex-wrap items-center justify-between gap-3 p-6">
        <div>
            <h2 class="text-2xl font-semibold">Log out</h2>
            <p class="opacity-80">See you soon.</p>
        </div>
        <form method="post" action="<?= e(url('logout')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn rounded-full"><?= icon('logout', 18) ?> Log out</button>
        </form>
    </div>

    <?php // Delete account. An admin account cannot be deleted here, so an admin only sees a note. ?>
    <?php if ($user['RoleName'] === 'admin'): ?>
        <div class="rounded-box border border-error/40 bg-error/5 p-6">
            <h2 class="text-2xl font-semibold text-error">Delete account</h2>
            <p class="mt-2 opacity-80">An admin account cannot be deleted here.</p>
        </div>
    <?php else: ?>
        <form method="post" action="<?= e(url('delete-account')) ?>" id="delete-account-form" class="rounded-box border border-error/40 bg-error/5 p-6">
            <?= csrf_field() ?>
            <h2 class="text-2xl font-semibold text-error">Delete account</h2>
            <p class="mt-2 opacity-80">This removes your profile, posts, comments and boards for good. We can't bring them back.</p>
            <label class="mt-4 block max-w-sm">
                <span class="mb-1 block font-bold">Your password</span>
                <input type="password" class="input input-bordered w-full" name="password" required maxlength="72" autocomplete="current-password" aria-describedby="delete-password-hint">
                <span id="delete-password-hint" class="mt-1 block text-sm opacity-80">We ask for it so nobody at an unlocked computer can delete your account.</span>
            </label>
            <?php // data-confirm opens the "Are you sure?" window (assets/js/app.js). Without JavaScript the button simply sends the form. ?>
            <button type="submit" class="btn btn-error btn-outline mt-4 rounded-full" data-confirm="Delete your account?" data-confirm-text="Everything you made on To Be Human will be removed. This cannot be undone." data-confirm-form="delete-account-form"><?= icon('trash', 16) ?> Delete my account</button>
        </form>
    <?php endif; ?>
<?php partial('settings_close'); ?>
