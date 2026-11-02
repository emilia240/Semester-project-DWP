<?php
/* Join step 3: show the saved community rules and let the member agree to them. */
partial('auth_open');
partial('join_steps', ['current' => 3]);
?>
<p class="mt-8 font-hand text-3xl text-accent">Step 3 of 3</p>
<h1 class="mt-1 text-5xl font-semibold">Our guidelines</h1>
<p class="mt-3 text-lg opacity-80">Last step. Please read how we treat each other here.</p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-error mt-6" role="alert"><span><?= e($errors['form']) ?></span></div>
<?php endif; ?>

<?php if ($rules !== null): ?>
    <div class="tbh-card mt-6 whitespace-pre-line p-5 text-lg"><?= e($rules) ?></div>

    <form method="post" action="<?= e(url('join-guidelines')) ?>" class="mt-6 space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="flex cursor-pointer items-start gap-3 rounded-box border border-accent/40 bg-accent/10 p-4">
                <input type="checkbox" class="checkbox checkbox-primary mt-0.5" name="agree" value="1"<?= field_attrs($errors, 'agree') ?>>
                <span>I have read the guidelines and I agree to follow them.</span>
            </label>
            <?= field_error($errors, 'agree') ?>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-full rounded-full">Join To Be Human <?= icon('arrow', 20) ?></button>
    </form>
<?php endif; ?>

<div class="mt-6 flex items-center justify-between text-sm">
    <a class="link" href="<?= e(url('join-crafts')) ?>">Back to my crafts</a>
    <form method="post" action="<?= e(url('logout')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="link">Log out and finish later</button>
    </form>
</div>
<?php partial('auth_close'); ?>
