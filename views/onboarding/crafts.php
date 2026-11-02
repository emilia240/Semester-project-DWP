<?php
/*
Join step 2: choose crafts. Uses $categories, $chosen, $errors and $inOnboarding.
The server checks the crafts[] values again before saving them.
*/
partial('auth_open');
if ($inOnboarding) { partial('join_steps', ['current' => 2]); }
?>
<p class="mt-8 font-hand text-3xl text-accent"><?= $inOnboarding ? 'Step 2 of 3' : 'Your crafts' ?></p>
<h1 class="mt-1 text-5xl font-semibold">What do you make?</h1>
<p class="mt-3 text-lg opacity-80">Pick every craft you work with. You can change this later.</p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error mt-6" role="alert"><span><?= e($errors['form']) ?></span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('join-crafts')) ?>" class="mt-6 space-y-5">
    <?= csrf_field() ?>

    <fieldset>
        <legend class="sr-only">Crafts</legend>
        <div class="grid gap-3 sm:grid-cols-2">
            <?php foreach ($categories as $craft): ?>
                <label class="flex cursor-pointer items-center gap-3 rounded-box border border-base-300 bg-base-100 p-3 has-[:checked]:border-primary has-[:checked]:bg-primary/10">
                    <input type="checkbox" class="checkbox checkbox-primary" name="crafts[]" value="<?= (int)$craft['CategoryID'] ?>"
                        <?= in_array((int)$craft['CategoryID'], $chosen, true) ? 'checked' : '' ?>>
                    <span><?= e($craft['CategoryName']) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'crafts') ?>
    </fieldset>

    <button type="submit" class="btn btn-primary btn-lg w-full rounded-full"><?= $inOnboarding ? 'Continue' : 'Save' ?> <?= icon('arrow', 20) ?></button>
</form>

<?php if ($inOnboarding): ?>
    <form method="post" action="<?= e(url('logout')) ?>" class="mt-6 text-center">
        <?= csrf_field() ?>
        <button type="submit" class="link text-sm">Log out and finish later</button>
    </form>
<?php else: ?>
    <p class="mt-6 text-center"><a class="link" href="<?= e(url('members')) ?>">Back to my account</a></p>
<?php endif; ?>
<?php partial('auth_close'); ?>
