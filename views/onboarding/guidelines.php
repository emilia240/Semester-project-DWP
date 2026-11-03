<?php
/*
Join step 3: show the saved community guidelines as cards and ask the member to agree.
The controller prepares the cards and checks the agreement even when JavaScript is off.
*/
partial('auth_open');
?>
<div class="flex items-center justify-between text-xs font-bold uppercase tracking-widest opacity-80"><span>Community guidelines</span><span>Step 3 of 3</span></div>
<progress class="progress progress-secondary mt-3 w-full" value="100" max="100" aria-label="Step 3 of 3"></progress>

<h1 class="mt-8 text-5xl font-semibold">Community guidelines</h1>
<p class="mt-3 text-lg opacity-80">Read these before you post. Acknowledging them is required.</p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error mt-6" role="alert"><span><?= e($errors['form']) ?></span></div>
<?php endif; ?>

<?php // Never offer agreement if the database has no usable rules. ?>
<?php if ($rules !== null): ?>
    <ol class="mt-6 space-y-3">
        <?php foreach ($cards as $i => [$title, $text]): ?>
            <li class="tbh-card p-5">
                <p class="text-xs font-bold uppercase tracking-widest opacity-80">Rule <?= $i + 1 ?></p>
                <h2 class="mt-1 text-xl font-semibold"><?= e($title) ?></h2>
                <?php if ($text !== ''): ?><p class="mt-1 whitespace-pre-line opacity-80"><?= e($text) ?></p><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>

    <form method="post" action="<?= e(url('join-guidelines')) ?>" class="mt-6" data-require-check>
        <?= csrf_field() ?>
        <label class="flex cursor-pointer items-start gap-3 rounded-box border border-accent/40 bg-accent/10 p-4">
            <input type="checkbox" class="checkbox checkbox-primary mt-0.5" name="agree" value="1" data-pledge<?= field_attrs($errors, 'agree') ?>>
            <span>I've read the guidelines and I'll only post work made by my own hands, without generative AI.</span>
        </label>
        <?= field_error($errors, 'agree') ?>
        <div class="mt-6 flex items-center justify-between gap-3">
            <a href="<?= e(url('join-crafts')) ?>" class="btn btn-ghost rounded-full"><?= icon('back', 18) ?> Back</a>
            <button type="submit" class="btn btn-primary btn-lg rounded-full" data-needs-check>Agree and finish <?= icon('arrow', 20) ?></button>
        </div>
    </form>
<?php endif; ?>

<?php // Logout uses its own protected POST form. ?>
<form method="post" action="<?= e(url('logout')) ?>" class="mt-6 text-center">
    <?= csrf_field() ?>
    <button type="submit" class="link text-sm">Log out and finish later</button>
</form>
<?php partial('auth_close'); ?>
