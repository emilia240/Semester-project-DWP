<?php
/*
"Maker of the week": a cut-out card with a taped photo. The maker is calculated by the view maker_of_the_week (the post with the most
likes + saves this week). We do not tell members how, so nobody chases likes. Visitors cannot open profiles, so the card opens the post.
The photo keeps its own shape: nothing is cropped, a blurred copy of it fills the empty space. Colours are theme tokens (light and dark).
Reading order, from big to small: the card title, the maker's handle, the tagline, then the link to the post.
Needs: $maker = the row from PostModel::makerOfTheWeek().
*/
$href = e(url('post', ['id' => $maker['PostID']]));
$file = ImageUploader::url($maker['CoverFile']);
?>
<?php // Leave a little room for the tilted card when a long title makes it taller. ?>
<aside class="relative mx-auto w-full max-w-2xl px-1 pt-6" aria-label="Maker of the week">
    <div class="relative rotate-[-1deg] rounded-[2rem] border-[3px] border-dashed border-accent bg-base-100 p-5 shadow-xl sm:p-6">
        <span class="absolute -top-5 left-10 rotate-[-90deg] rounded-full bg-base-100 px-1 text-accent" aria-hidden="true"><?= craft_icon('scissors', 34) ?></span>

        <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
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
                <h2 class="font-hand text-4xl leading-none text-accent sm:text-5xl">Maker of the week</h2>
                <svg class="mx-auto mt-1 text-accent sm:mx-0" width="150" height="10" viewBox="0 0 150 10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M3 6c12-6 22 6 34 0s22 6 34 0 22 6 34 0 22 6 40 0"/></svg>

                <?php // Just the handle, in a normal size: the photo and the card title are the stars here. ?>
                <p class="mt-4 flex items-center justify-center gap-2 sm:justify-start">
                    <?= avatar($maker, 40) ?>
                    <span class="wrap-anywhere text-lg font-semibold">@<?= e($maker['Username']) ?></span>
                </p>
                <?php if (!empty($maker['Tagline'])): ?><p class="wrap-anywhere mt-2 text-sm opacity-80"><?= e($maker['Tagline']) ?></p><?php endif; ?>

                <?php // The title can shrink and wrap without pushing the arrow or the card sideways. ?>
                <a href="<?= $href ?>" class="mt-4 inline-flex max-w-full items-center gap-1 text-sm font-semibold underline decoration-accent decoration-2 underline-offset-4 hover:text-accent"><span class="min-w-0 wrap-anywhere">See “<?= e($maker['PostTitle']) ?>”</span> <?= icon('arrow', 16) ?></a>
            </div>
        </div>
    </div>
</aside>
