<?php
/*
"Maker of the week": a playful cut-out card. The maker is calculated by the view maker_of_the_week (the post with the most
likes + saves this week). We do not tell members how, so nobody chases likes. Visitors cannot open profiles, so the card opens the post.
The photo keeps its own shape: nothing is cropped, a blurred copy of it fills the empty space. Colours are theme tokens (light and dark).
Needs: $maker = the row from PostModel::makerOfTheWeek().
*/
$href = e(url('post', ['id' => $maker['PostID']]));
$file = ImageUploader::url($maker['CoverFile']);
?>
<?php // Leave a little room for the tilted card when a long title makes it taller. ?>
<aside class="relative mx-auto w-full max-w-2xl px-1 pt-6" aria-label="Maker of the week">
    <span class="pointer-events-none absolute left-0 top-16 text-secondary opacity-60" aria-hidden="true"><?= craft_icon('star', 28, 'rotate-[-14deg]') ?></span>
    <span class="pointer-events-none absolute bottom-10 right-0 text-primary opacity-60" aria-hidden="true"><?= craft_icon('star', 22, 'rotate-[16deg]') ?></span>

    <div class="relative rotate-[-1deg] rounded-[2rem] border-[3px] border-dashed border-accent bg-base-100 p-5 shadow-xl sm:p-6">
        <span class="absolute -top-5 left-10 rotate-[-90deg] rounded-full bg-base-100 px-1 text-accent" aria-hidden="true"><?= craft_icon('scissors', 34) ?></span>
        <span class="absolute -bottom-8 -right-1 grid h-24 w-24 rotate-[12deg] place-items-center rounded-full bg-primary text-center text-primary-content shadow-md" aria-hidden="true">
            <span class="flex flex-col items-center leading-none"><?= craft_icon('star', 22) ?><span class="mt-1 font-hand text-xl">this week’s<br>star!</span></span>
        </span>

        <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
            <a href="<?= $href ?>" class="group relative block w-3/4 shrink-0 rotate-[2deg] self-center sm:w-[46%]" aria-label="Open <?= e($maker['PostTitle']) ?>">
                <span class="absolute -top-3 left-1/2 z-10 h-6 w-20 -translate-x-1/2 rotate-[-4deg] bg-accent/40" aria-hidden="true"></span>
                <span class="relative block overflow-hidden rounded-b-2xl rounded-t-[999px] border-4 border-base-content/80 bg-base-300 shadow-md transition group-hover:-translate-y-1">
                    <span class="relative block aspect-[4/5] w-full">
                        <img src="<?= e($file) ?>" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full scale-125 object-cover opacity-60 blur-2xl">
                        <img src="<?= e($file) ?>" alt="<?= e($maker['AltText'] ?? $maker['PostTitle']) ?>" class="relative h-full w-full object-contain">
                    </span>
                </span>
            </a>

            <div class="min-w-0 flex-1 text-center sm:text-left">
                <p class="font-hand text-4xl leading-none text-accent sm:text-5xl">Maker of the week</p>
                <svg class="mx-auto mt-1 text-accent sm:mx-0" width="150" height="10" viewBox="0 0 150 10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M3 6c12-6 22 6 34 0s22 6 34 0 22 6 34 0 22 6 40 0"/></svg>
                <div class="mt-5 flex flex-col items-center gap-3 sm:items-start">
                    <?= avatar($maker, 64) ?>
                    <div class="min-w-0">
                        <?php // Wrap long names so they stay inside the card. ?>
                        <p class="wrap-anywhere font-display text-2xl font-semibold leading-tight sm:text-3xl"><?= e($maker['FirstName'] . ' ' . $maker['LastName']) ?></p>
                        <p class="wrap-anywhere text-sm opacity-80">@<?= e($maker['Username']) ?></p>
                        <?php if (!empty($maker['Tagline'])): ?><p class="mt-2"><span class="badge badge-sm border-0 bg-base-300 font-semibold text-base-content"><?= e($maker['Tagline']) ?></span></p><?php endif; ?>
                    </div>
                </div>
                <?php // The title can shrink and wrap without pushing the arrow or the card sideways. ?>
                <a href="<?= $href ?>" class="mt-5 inline-flex max-w-full items-center gap-1 font-bold underline decoration-accent decoration-2 underline-offset-4 hover:text-accent"><span class="min-w-0 wrap-anywhere"><?= e($maker['PostTitle']) ?></span> <?= icon('arrow', 16) ?></a>
            </div>
        </div>
    </div>
</aside>
