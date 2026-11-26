<?php
/*
A small photo card (from the daisy design). Used on the profile page, later also for "More like this", boards and challenge entries.
The photo keeps its own shape: nothing is cropped, there is only a maximum height.
Needs:    $p          one card row from PostModel (PostID, PostTitle, AltText, CoverFile, AuthorUsername)
Optional: $label      a small label on the photo, for example "Draft"
          $labelClass the colour of the label, for example "badge-warning" (default: "badge-neutral")
          $href       where the card links to (default: the post page)
*/
$labelText  = '';
if (isset($label)) {
    $labelText = $label;
}

$labelColor = 'badge-neutral';
if (isset($labelClass)) {
    $labelColor = $labelClass;
}

$link = url('post', ['id' => $p['PostID']]);
if (isset($href)) {
    $link = $href;
}

// The alt text describes the photo for screen readers. If the maker wrote none, the title is used.
$alt = $p['PostTitle'];
if ($p['AltText'] !== null && $p['AltText'] !== '') {
    $alt = $p['AltText'];
}
?>
<a href="<?= e($link) ?>" class="group mb-6 block break-inside-avoid">
    <div class="relative overflow-hidden rounded-box bg-base-300">
        <img src="<?= e(ImageUploader::url($p['CoverFile'], true)) ?>" <?= image_attrs($p['CoverFile'], true) ?> alt="<?= e($alt) ?>" loading="lazy" class="mx-auto h-auto max-h-[40rem] w-full object-contain">
        <?php if ($labelText !== ''): ?>
            <span class="badge <?= e($labelColor) ?> absolute left-2 top-2 border-0 text-xs font-bold"><?= e($labelText) ?></span>
        <?php endif; ?>
    </div>
    <p class="wrap-anywhere mt-2 line-clamp-2 font-bold leading-snug group-hover:underline"><?= e($p['PostTitle']) ?></p>
    <p class="wrap-anywhere text-sm opacity-80">by <?= e($p['AuthorUsername']) ?></p>
</a>
