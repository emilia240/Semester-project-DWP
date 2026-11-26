<?php
/*
The top of every Settings page: the big word "Settings" and the sidebar with the sections.
Use it like this (the same idea as auth_open / auth_close):
    partial('settings_open', ['active' => 'profile']);
    ... the cards of this section ...
    partial('settings_close');
$active = which sidebar item is dark: 'profile' or 'account'.
*/

// The sidebar items. 'key' is what $active is compared with, 'page' is the page the link opens.
$items = [
    ['key' => 'profile', 'text' => 'Profile', 'page' => 'edit-profile', 'icon' => 'user'],
    ['key' => 'account', 'text' => 'Account', 'page' => 'members',      'icon' => 'lock'],
];
?>
<section class="mx-auto max-w-7xl px-4 pb-16 pt-10 sm:px-16 md:px-24 lg:px-40">
    <h1 class="text-5xl font-semibold sm:text-6xl">Settings</h1>

    <?php // On a phone the sidebar is a row on top, on a big screen it is a column on the left. ?>
    <div class="mt-8 grid gap-8 lg:grid-cols-[14rem_1fr]">
        <nav aria-label="Settings sections">
            <ul class="menu menu-horizontal w-full gap-1 rounded-box bg-base-100 p-4 shadow-sm lg:menu-vertical lg:flex-col">
                <?php foreach ($items as $item): ?>
                    <?php
                    // The section I am in is dark (menu-active) and is marked for screen readers (aria-current).
                    $linkClass = 'px-4 py-3 text-base font-bold';
                    $current   = '';
                    if ($active === $item['key']) {
                        $linkClass = 'px-4 py-3 text-base font-bold menu-active';
                        $current   = ' aria-current="page"';
                    }
                    ?>
                    <li><a href="<?= e(url($item['page'])) ?>" class="<?= e($linkClass) ?>"<?= $current ?>><?= icon($item['icon'], 18) ?> <?= e($item['text']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="space-y-6">
