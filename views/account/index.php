<?php
/* Settings > Account. Needs: $user (the logged-in member, from index.php), $posts, $birthdate (like 1994-02-11) and $settings (the four switches).
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

    <?php // Change password has its own page (it asks for the current password); the sidebar keeps "Account" dark there too. ?>
    <div class="tbh-card flex flex-wrap items-center justify-between gap-3 p-6">
        <div>
            <h2 class="text-2xl font-semibold">Password</h2>
            <p class="opacity-80">Choose a new one whenever you like.</p>
        </div>
        <a class="btn btn-outline rounded-full" href="<?= e(url('password')) ?>"><?= icon('lock', 18) ?> Change my password</a>
    </div>

    <h2 class="mt-6 font-display text-3xl font-semibold">My posts</h2>
    <p class="mt-1 text-sm opacity-80">Drafts are only visible to you.</p>
    <?php if ($posts): ?>
        <div class="mt-6 columns-1 gap-8 sm:columns-2">
            <?php foreach ($posts as $p) { partial('post_card', ['p' => $p, 'showStatus' => true, 'back' => 'members']); } ?>
        </div>
    <?php else: ?>
        <p class="mt-4 opacity-80">You have not posted anything yet.</p>
        <p class="mt-4"><a href="<?= e(url('create')) ?>" class="btn btn-primary rounded-full">Make your first post</a></p>
    <?php endif; ?>
<?php partial('settings_close'); ?>
