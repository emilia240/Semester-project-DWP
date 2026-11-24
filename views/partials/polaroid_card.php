<?php
/*
A small "polaroid" for the box "Coolest this week": a paper frame, a little tilt and a piece of tape.
The picture keeps its own shape (nothing is cropped). Needs: $p = one card row from PostModel, $i = 0, 1, 2 ... (decides tilt and tape colour).
*/
$i = $i ?? 0;
$tilts = ['-rotate-2', 'rotate-[1.5deg]', 'rotate-2', '-rotate-[1.5deg]'];
$tapes = ['bg-accent/40', 'bg-secondary/40', 'bg-primary/35', 'bg-info/35'];
?>
<a href="<?= e(url('post', ['id' => $p['PostID']])) ?>" class="group relative mb-6 block break-inside-avoid <?= $tilts[$i % 4] ?> rounded-md border border-base-content/15 bg-base-100 p-2 pb-3 shadow-md transition hover:rotate-0 hover:shadow-xl focus-visible:rotate-0">
    <span class="absolute -top-2 left-1/2 z-10 h-4 w-12 -translate-x-1/2 rotate-[-5deg] <?= $tapes[$i % 4] ?>" aria-hidden="true"></span>
    <span class="block overflow-hidden rounded-sm bg-base-300">
        <img src="<?= e(ImageUploader::url($p['CoverFile'], true)) ?>" <?= image_attrs($p['CoverFile'], true) ?> alt="<?= e($p['AltText'] ?? $p['PostTitle']) ?>" loading="lazy" class="mx-auto h-auto max-h-[40rem] w-full object-contain">
    </span>
    <?php // Wrap long words so the full title stays inside the small card. ?>
    <span class="mt-2 block wrap-anywhere px-1 font-hand text-xl leading-tight group-hover:underline"><?= e($p['PostTitle']) ?></span>
    <?php // A long username should stay inside the polaroid too. ?>
    <span class="block wrap-anywhere px-1 text-sm opacity-80">by <?= e($p['AuthorUsername']) ?></span>
</a>
