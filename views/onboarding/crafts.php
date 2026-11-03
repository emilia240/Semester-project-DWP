<?php
/*
Join step 2: choose crafts or change saved choices.
The controller checks every craft ID before saving it. JavaScript updates the hint and button.
*/
partial('auth_open');
?>
<?php // Show progress only while the member is completing the join steps. ?>
<?php if ($inOnboarding): ?>
    <div class="flex items-center justify-between text-xs font-bold uppercase tracking-widest opacity-80"><span>What you make</span><span>Step 2 of 3</span></div>
    <progress class="progress progress-secondary mt-3 w-full" value="66" max="100" aria-label="Step 2 of 3"></progress>
<?php endif; ?>

<h1 class="<?= $inOnboarding ? 'mt-8' : '' ?> text-5xl font-semibold"><?= $inOnboarding ? 'What do you love to make?' : 'Your crafts' ?></h1>
<p class="mt-3 text-lg opacity-80">Pick as many as you like. This seeds your feed, and you can change it any time.</p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error mt-6" role="alert"><span><?= e($errors['form']) ?></span></div>
<?php endif; ?>

<?php // The boxes remain usable without JavaScript, and PHP validates them on submission. ?>
<form method="post" action="<?= e(url('join-crafts')) ?>" class="mt-8" data-min-select>
    <?= csrf_field() ?>
    <fieldset<?= field_attrs($errors, 'crafts') ?>>
        <legend class="sr-only">Your crafts</legend>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <?php foreach ($categories as $craft): ?>
                <label class="group cursor-pointer">
                    <input type="checkbox" class="peer sr-only" name="crafts[]" value="<?= (int)$craft['CategoryID'] ?>"
                        <?= in_array((int)$craft['CategoryID'], $chosen, true) ? 'checked' : '' ?>>
                    <span class="flex h-full min-w-0 items-center gap-2 rounded-box border-2 border-base-300 bg-base-100 p-4 text-sm font-bold transition peer-checked:border-secondary peer-checked:bg-secondary/15 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-info">
                        <span class="min-w-0 break-words"><?= e($craft['CategoryName']) ?></span>
                        <span class="ml-auto hidden shrink-0 text-secondary group-has-[:checked]:block" aria-hidden="true"><?= icon('check', 18) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'crafts') ?>
    </fieldset>
    <p class="mt-4 text-sm opacity-80" data-hint aria-live="polite">Choose at least one to continue.</p>
    <div class="mt-6 flex items-center justify-between gap-3">
        <?php if ($inOnboarding): ?>
            <button type="submit" form="logout-form" class="btn btn-ghost rounded-full">Log out</button>
        <?php else: ?>
            <a href="<?= e(url('members')) ?>" class="btn btn-ghost rounded-full"><?= icon('back', 18) ?> Back</a>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary btn-lg rounded-full" data-needs-select><?= $inOnboarding ? 'Continue' : 'Save' ?> <?= icon('arrow', 20) ?></button>
    </div>
</form>
<?php if ($inOnboarding): ?>
    <?php // A separate form avoids nesting forms; the logout button points to its ID. ?>
    <form id="logout-form" method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?></form>
<?php endif; ?>
<?php partial('auth_close'); ?>
