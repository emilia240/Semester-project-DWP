<?php
/*
Edit profile: the tagline, the about text and the badge (the "Your profile" card of the daisy design).
Needs: $old (tagline, about, badge: what the form shows now) and $errors (field name => message).
Everything is checked again in PHP (ProfileController::edit), the maxlength here only helps the person typing.
Later steps add more to this form: social links (step 3), names (step 6).
*/
?>
<section class="mx-auto max-w-2xl px-4 pb-16 pt-10 sm:px-8">
    <h1 class="text-5xl font-semibold">Edit profile</h1>

    <?php if (isset($errors['form'])): ?>
        <div class="alert alert-error mt-6" role="alert"><span><?= e($errors['form']) ?></span></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('edit-profile')) ?>" class="tbh-card mt-8 space-y-5 p-6">
        <?= csrf_field() ?>
        <h2 class="text-2xl font-semibold">Your profile</h2>

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

        <div class="flex gap-3">
            <button type="submit" class="btn btn-primary rounded-full">Save profile</button>
            <a class="btn btn-ghost rounded-full" href="<?= e(url('profile')) ?>">View my profile</a>
        </div>
    </form>
</section>
