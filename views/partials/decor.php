<?php
/*
Quiet craft doodles (brush, scissors, yarn ...) in the empty side margins of the page while you scroll.
Purely decoration: hidden from screen readers, not clickable, and only on wide screens (a phone has no spare margin).

Each doodle: [icon, side (l/r), top in % of the page, size in px, tilt in degrees, colour, drift].
"drift" = how far it slides against the scroll. assets/js/app.js reads the data-* attributes and does the placing,
because the Content-Security-Policy does not allow style="..." in the HTML.
*/
$items = [
    ['brush', 'l', 3, 92, -24, 'text-accent', 0.06],
    ['yarn', 'r', 7, 96, 12, 'text-primary', -0.05],
    ['scissors', 'l', 15, 88, 18, 'text-secondary', 0.08],
    ['leaf', 'r', 21, 80, -20, 'text-secondary', 0.05],
    ['rings', 'l', 28, 84, 0, 'text-accent', -0.06],
    ['pencil', 'r', 35, 92, 32, 'text-primary', 0.07],
    ['button', 'l', 42, 70, -10, 'text-primary', -0.04],
    ['spoon', 'r', 49, 96, -28, 'text-accent', 0.06],
    ['needle', 'l', 56, 90, 14, 'text-secondary', 0.05],
    ['palette', 'r', 63, 92, 10, 'text-primary', -0.07],
    ['ruler', 'l', 70, 96, -32, 'text-accent', 0.06],
    ['hoop', 'r', 77, 88, -8, 'text-secondary', -0.05],
    ['hook', 'l', 84, 92, 22, 'text-primary', 0.07],
    ['thimble', 'r', 90, 72, 8, 'text-accent', -0.04],
];
?>
<div class="pointer-events-none absolute inset-0 -z-10 hidden overflow-hidden lg:block" aria-hidden="true" data-decor>
    <div class="relative mx-auto h-full max-w-[120rem]">
        <?php foreach ($items as [$icon, $side, $top, $size, $rotation, $color, $drift]): ?>
            <span class="tbh-decor absolute <?= e($color) ?>" data-side="<?= $side ?>" data-x="<?= 28 + ($top * 7 % 30) ?>" data-top="<?= $top ?>" data-rot="<?= $rotation ?>" data-drift="<?= $drift ?>"><?= craft_icon($icon, $size) ?></span>
        <?php endforeach; ?>
    </div>
</div>
