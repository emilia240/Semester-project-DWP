<?php
/*
"Maker of the week": a cut-out card with a taped polaroid. The maker is calculated by the view maker_of_the_week (the post with the most
likes + saves this week). We do not tell members how, so nobody chases likes. Visitors cannot open profiles, so the card opens the post.
The photo keeps its own shape: nothing is cropped, a blurred copy of it fills the empty space. Colours are theme tokens (light and dark).
Reading order, from big to small: the card title, the maker's username, the tagline, then the link to the post.
Needs: $maker = the row from PostModel::makerOfTheWeek(), $comments = the newest comments under that post (may be empty).
*/
$href = e(url('post', ['id' => $maker['PostID']]));
$file = ImageUploader::url($maker['CoverFile']);
$comments = $comments ?? [];
?>
<?php // Leave a little room for the tilted card when a long title makes it taller. ?>
<aside class="relative mx-auto w-full max-w-2xl px-1 pt-6" aria-label="Maker of the week" data-maker-card>
    <div class="relative rotate-[-1deg] rounded-[2rem] border-[3px] border-dashed border-accent bg-base-100 p-5 shadow-xl sm:p-6">
        <span class="absolute -top-5 left-10 rotate-[-90deg] rounded-full bg-base-100 px-1 text-accent" aria-hidden="true"><?= craft_icon('scissors', 34) ?></span>

        <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
            <?php // A paper frame and tape match the polaroids in "Coolest this week". ?>
            <a href="<?= $href ?>" class="group relative block w-3/4 shrink-0 rotate-[2deg] self-center rounded-md border border-base-content/15 bg-base-100 p-2 pb-3 shadow-md transition hover:rotate-0 hover:shadow-xl focus-visible:rotate-0 sm:w-[46%]" aria-label="Open <?= e($maker['PostTitle']) ?>">
                <span class="absolute -top-3 left-1/2 z-10 h-6 w-20 -translate-x-1/2 rotate-[-4deg] bg-accent/40" aria-hidden="true"></span>
                <span class="relative block overflow-hidden rounded-sm bg-base-300">
                    <span class="relative block aspect-[4/5] w-full">
                        <img src="<?= e($file) ?>" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full scale-125 object-cover opacity-60 blur-2xl">
                        <img src="<?= e($file) ?>" alt="<?= e($maker['AltText'] ?? $maker['PostTitle']) ?>" class="relative h-full w-full object-contain">
                    </span>
                </span>
                <?php // Long titles wrap inside the frame instead of stretching it. ?>
                <span class="mt-2 block wrap-anywhere px-1 font-hand text-xl leading-tight group-hover:underline"><?= e($maker['PostTitle']) ?></span>
            </a>

            <div class="min-w-0 flex-1 text-center sm:text-left">
                <h2 class="font-hand text-3xl leading-none text-accent sm:text-4xl">Maker of the week</h2>
                <svg class="mx-auto mt-1 text-accent sm:mx-0" width="150" height="10" viewBox="0 0 150 10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M3 6c12-6 22 6 34 0s22 6 34 0 22 6 34 0 22 6 40 0"/></svg>

                <?php // Just the username, in the same size, beside the avatar. ?>
                <p class="mt-4 flex items-center justify-center gap-2 sm:justify-start">
                    <?= avatar($maker, 40) ?>
                    <span class="wrap-anywhere text-lg font-semibold"><?= e($maker['Username']) ?></span>
                </p>
                <?php if (!empty($maker['Tagline'])): ?><p class="wrap-anywhere mt-2 text-sm opacity-80"><?= e($maker['Tagline']) ?></p><?php endif; ?>

                <?php if ($comments): ?>
                    <?php // The newest comments, one at a time. All of them sit in the same grid cell, so the card never jumps. Without JavaScript the first one stays. ?>
                    <div class="mt-4 text-left" data-comments data-interval="5000" role="group" aria-labelledby="maker-comments-h">
                        <h3 id="maker-comments-h" class="mb-2 flex items-center gap-2 text-sm font-semibold"><?= icon('comment', 16) ?> Latest comments</h3>
                        <div class="grid rounded-2xl bg-base-200 px-4 py-3">
                            <?php foreach ($comments as $i => $c): ?>
                                <?php // Long comments are trimmed to three lines; the full text is on the post. ?>
                                <blockquote class="tbh-fade col-start-1 row-start-1 min-w-0 <?= $i === 0 ? 'is-active' : '' ?>" data-comment<?= $i === 0 ? '' : ' aria-hidden="true"' ?>>
                                    <?php // Bold and italic work, but links are plain text so hidden comments cannot receive focus. ?>
                                    <p class="line-clamp-3 wrap-anywhere text-sm leading-snug"><?= preg_replace('~<a\b[^>]*>(.*?)</a>~s', '$1', format_text($c['CommentText'])) ?></p>
                                </blockquote>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php // The title wraps inside the card even when it contains a long word. ?>
                <a href="<?= $href . ($comments ? '#comments' : '') ?>" class="mt-4 inline-flex max-w-full items-center gap-1 text-sm font-semibold underline decoration-accent decoration-2 underline-offset-4 hover:text-accent"><span class="min-w-0 wrap-anywhere">See “<?= e($maker['PostTitle']) ?>”<?= $comments ? ' and comments' : '' ?></span></a>
            </div>
        </div>
    </div>
</aside>
