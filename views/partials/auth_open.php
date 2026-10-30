<?php
/*
The "split screen" used by log in, join and the password pages: a calm picture panel on the left
(only on wide screens), the form on the right. Close it with partial('auth_close').
The panel is plain colour and craft doodles for now; a real picture chosen by the admin can come later.
*/
?>
<div class="grid min-h-screen lg:grid-cols-[1fr_1.05fr]">
    <aside class="relative hidden overflow-hidden bg-base-300 lg:block" aria-hidden="true">
        <a href="<?= e(url('home')) ?>" class="absolute left-10 top-8 z-10 font-display text-3xl font-bold">To Be Human</a>
        <span class="absolute left-16 top-40 -rotate-12 text-accent"><?= craft_icon('brush', 150) ?></span>
        <span class="absolute right-20 top-56 rotate-12 text-primary"><?= craft_icon('scissors', 130) ?></span>
        <span class="absolute left-40 top-[26rem] rotate-6 text-secondary"><?= craft_icon('yarn', 160) ?></span>
        <span class="absolute bottom-48 right-24 -rotate-6 text-accent"><?= craft_icon('pencil', 120) ?></span>
        <p class="absolute bottom-10 left-10 right-10 font-hand text-5xl leading-tight">Made slowly, by hand.</p>
    </aside>
    <main id="main" class="flex flex-col px-6 py-8 sm:px-12">
        <a href="<?= e(url('home')) ?>" class="font-display text-2xl font-bold lg:hidden">To Be Human</a>
        <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-8">
            <?php partial('flash'); ?>
