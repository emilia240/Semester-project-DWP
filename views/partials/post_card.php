<?php
/*
One post in a list (front page, My account). Needs: $p = one row from PostModel::latest() / forUser().
Optional: $showStatus (true = show a Draft / Published label, only on the member's own list).
Optional: $back = which page the heart sends a member back to when JavaScript is off: 'home' or 'members' (default: the post page).
*/
$showStatus = $showStatus ?? false;
$back = $back ?? 'post';
$cats = $p['Categories'] !== null ? explode('|', $p['Categories']) : [];
$tags = $p['Tags'] !== null ? explode('|', $p['Tags']) : [];            // free tags, only letters, digits, - and _, so '|' cannot be inside one
$link = e(url('post', ['id' => $p['PostID']]));
$author = ['UserID' => $p['UserID'], 'FirstName' => $p['AuthorFirstName'], 'LastName' => $p['AuthorLastName']];
?>
<article class="mb-10 break-inside-avoid px-1">
    <header class="flex items-center gap-3 pb-3">
        <?= avatar($author, 40) ?>
        <div class="min-w-0 flex-1">
            <?php // Keep the full username visible even when the card is narrow. ?>
            <p class="break-all font-bold"><?= e($p['AuthorUsername']) ?></p>
            <p class="whitespace-nowrap text-sm opacity-80"><?= e(when($p['UploadedAt'])) ?></p>
        </div>
        <?php if ($showStatus): ?>
            <span class="badge <?= $p['IsDraft'] ? 'badge-warning' : 'badge-success' ?> badge-sm font-bold"><?= $p['IsDraft'] ? 'Draft' : 'Published' ?></span>
        <?php endif; ?>
        <?php // Keep the craft badges on the right, with room for the username. ?>
        <div class="hidden max-w-[45%] flex-wrap justify-end gap-1 sm:flex">
            <?php foreach ($cats as $c): ?><span class="badge badge-ghost badge-sm h-auto max-w-full break-all whitespace-normal"><?= e($c) ?></span><?php endforeach; ?>
        </div>
    </header>

    <?php if ($p['PostDescription'] !== null && $p['PostDescription'] !== ''): ?>
        <?php // Wrap long words and links before limiting the story to four lines. ?>
        <p class="line-clamp-4 wrap-anywhere pb-3 leading-relaxed"><?= format_text($p['PostDescription']) ?></p>
    <?php endif; ?>

    <?php // Use the small image so a feed with many posts loads faster. ?>
    <a href="<?= $link ?>" class="block bg-base-300" aria-label="Open <?= e($p['PostTitle']) ?>">
        <img src="<?= e(ImageUploader::url($p['CoverFile'], true)) ?>" <?= image_attrs($p['CoverFile'], true) ?> alt="<?= e($p['AltText'] ?? $p['PostTitle']) ?>" loading="lazy" class="mx-auto h-auto max-h-[30rem] w-full object-contain">
    </a>

    <?php // Long titles can wrap inside the card rather than spilling into another column. ?>
    <h3 class="mt-3 wrap-anywhere font-display text-xl font-semibold leading-snug"><a href="<?= $link ?>" class="hover:underline"><?= e($p['PostTitle']) ?></a></h3>

    <?php if ($tags): ?>
        <?php // A long tag can wrap instead of reaching into the next card. ?>
        <p class="mt-1 flex flex-wrap gap-2 text-sm"><?php foreach ($tags as $t): ?><span class="min-w-0 max-w-full wrap-anywhere font-bold text-primary">#<?= e($t) ?></span><?php endforeach; ?></p>
    <?php endif; ?>

    <?php // A draft has no heart (it is not public), so no footer either. ?>
    <?php if (!$p['IsDraft']): ?>
        <footer class="mt-1 flex items-center gap-1 text-base-content/80">
            <?php partial('like_button', ['p' => $p, 'back' => $back]); ?>
            <?php // The count includes visible comments and replies; the link opens the comment section. ?>
            <a href="<?= $link ?>#comments" class="btn btn-ghost btn-sm gap-1" aria-label="<?= (int)$p['CommentCount'] ?> comments"><?= icon('comment', 18) ?> <?= (int)$p['CommentCount'] ?></a>
            <button type="button" class="btn btn-ghost btn-sm ml-auto" data-copy="<?= $link ?>" aria-label="Copy link"><?= icon('link', 18) ?></button>
        </footer>
    <?php endif; ?>
</article>
