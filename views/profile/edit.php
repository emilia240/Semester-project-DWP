<?php
/*
Edit profile: the tagline, the about text, the badge and the links (the "Your profile" card of the daisy design).
Needs: $old (first_name, last_name, username, tagline, about, badge, link1, link2, link3: what the form shows now) and $errors (field name => message).
Everything is checked again in PHP (ProfileController::edit), the maxlength here only helps the person typing.
The names (first name, last name, username) use the same rules as sign-up.
It sits inside the Settings layout (the sidebar): see views/partials/settings_open.php.
*/
partial('settings_open', ['active' => 'profile']);
?>
    <?php if (isset($errors['form'])): ?>
        <div class="alert alert-error" role="alert"><span><?= e($errors['form']) ?></span></div>
    <?php endif; ?>

    <?php // enctype="multipart/form-data" is needed so that the form can send a FILE (the profile photo). ?>
    <form method="post" action="<?= e(url('edit-profile')) ?>" enctype="multipart/form-data" class="tbh-card space-y-5 p-6">
        <?= csrf_field() ?>
        <h2 class="text-2xl font-semibold">Your profile</h2>

        <?php // The profile photo: what it looks like now, a file field for a new one, and a box to remove it. ?>
        <?php
        // A red border on the file field when the photo was refused (file-input-error is the red border of a file field).
        $photoClass = '';
        if (isset($errors['avatar'])) {
            $photoClass = 'file-input-error';
        }
        ?>
        <div>
            <span class="mb-2 block font-bold">Photo</span>
            <div class="flex flex-wrap items-center gap-4">
                <?= avatar($user, 64) ?>
                <div class="min-w-0 flex-1 space-y-2">
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="file-input file-input-bordered w-full max-w-sm <?= e($photoClass) ?>" aria-label="Choose a new photo"<?= field_attrs($errors, 'avatar') ?>>
                    <?php if (!empty($user['AvatarFile'])): ?>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input type="checkbox" class="checkbox checkbox-sm" name="remove_avatar" value="1">
                            <span>Remove my photo</span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
            <?= field_error($errors, 'avatar') ?>
            <span class="mt-1 block text-sm opacity-80">JPG, PNG or WEBP, up to <?= e(ImageUploader::fileLimitText()) ?>. It is cut to a square from the middle. Without a photo, your initials are shown. If the form shows a message, choose the photo again.</span>
        </div>

        <?php // The names. First and last name side by side on a big screen, under each other on a phone. ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block font-bold">First name</span>
                <input class="input input-bordered w-full <?= field_class($errors, 'first_name') ?>" name="first_name" maxlength="80" value="<?= e($old['first_name']) ?>" autocomplete="given-name"<?= field_attrs($errors, 'first_name') ?>>
                <?= field_error($errors, 'first_name') ?>
            </label>
            <label class="block">
                <span class="mb-1 block font-bold">Last name</span>
                <input class="input input-bordered w-full <?= field_class($errors, 'last_name') ?>" name="last_name" maxlength="80" value="<?= e($old['last_name']) ?>" autocomplete="family-name"<?= field_attrs($errors, 'last_name') ?>>
                <?= field_error($errors, 'last_name') ?>
            </label>
        </div>

        <label class="block">
            <span class="mb-1 block font-bold">Username</span>
            <input class="input input-bordered w-full <?= field_class($errors, 'username') ?>" name="username" maxlength="30" value="<?= e($old['username']) ?>" autocomplete="username"<?= field_attrs($errors, 'username') ?>>
            <?= field_error($errors, 'username') ?>
            <span class="mt-1 block text-sm opacity-80">3 to 30 letters, numbers or underscores. Other members see it next to your posts.</span>
        </label>

        <label class="block">
            <span class="mb-1 block font-bold">Tagline</span>
            <input class="input input-bordered w-full <?= field_class($errors, 'tagline') ?>" name="tagline" maxlength="80" value="<?= e($old['tagline']) ?>" placeholder="One line about what you make"<?= field_attrs($errors, 'tagline') ?>>
            <?= field_error($errors, 'tagline') ?>
        </label>

        <label class="block">
            <span class="mb-1 block font-bold">About you</span>
            <textarea class="textarea textarea-bordered w-full <?= field_class($errors, 'about') ?>" rows="4" name="about" maxlength="400"<?= field_attrs($errors, 'about') ?>><?= e($old['about']) ?></textarea>
            <?= field_error($errors, 'about') ?>
        </label>

        <label class="block">
            <span class="mb-1 block font-bold">Badge</span>
            <input class="input input-bordered w-full sm:w-80 <?= field_class($errors, 'badge') ?>" name="badge" maxlength="30" value="<?= e($old['badge']) ?>" placeholder="e.g. Knitter, or Potion Potter"<?= field_attrs($errors, 'badge') ?>>
            <?= field_error($errors, 'badge') ?>
            <span class="mt-1 block text-sm opacity-80">A little label next to your name. Write whatever describes you.</span>
        </label>

        <?php // The links: three fields. A field you leave empty is skipped. https:// is added for you when you leave it out. ?>
        <fieldset>
            <legend class="mb-1 font-bold">Your links</legend>
            <p class="mb-3 text-sm opacity-80">Your shop, your Instagram, your website. Visitors get a “do you trust this link?” warning before they leave.</p>
            <div class="space-y-2">
                <?php
                $linkFields = ['link1' => 'Link 1', 'link2' => 'Link 2', 'link3' => 'Link 3'];
                foreach ($linkFields as $field => $label):
                ?>
                    <div>
                        <input class="input input-bordered w-full <?= field_class($errors, $field) ?>" name="<?= e($field) ?>" maxlength="255" value="<?= e($old[$field]) ?>" placeholder="instagram.com/you" aria-label="<?= e($label) ?>"<?= field_attrs($errors, $field) ?>>
                        <?= field_error($errors, $field) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="flex gap-3">
            <button type="submit" class="btn btn-primary rounded-full">Save profile</button>
            <a class="btn btn-ghost rounded-full" href="<?= e(url('profile')) ?>">View my profile</a>
        </div>
    </form>
<?php partial('settings_close'); ?>
