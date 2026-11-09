<?php
/*
The form to make a post, and to edit one (the same file, filled in when $edit is set).
Needs: $edit (the post being edited, or null), $old (the values to show), $errors, $categories (all crafts),
       $challenge (the running challenge or null), $fileLimit (text like "5 MB").
The tag box, the 3-craft limit, the picture previews and the Preview window are in assets/js/app.js.
All of that is only help: PHP checks everything again (controllers/PostController.php).
*/
$isEdit      = $edit !== null;
$published   = $isEdit && !$edit['IsDraft'];
$cover       = null;
$steps       = [];
foreach ($isEdit ? $edit['images'] : [] as $image) {
    if ((int)$image['Position'] === 1) {
        $cover = $image;
    } else {
        $steps[] = $image;
    }
}
?>
<section class="mx-auto max-w-3xl px-4 pb-16 pt-10">
    <h1 class="text-5xl font-semibold sm:text-6xl"><?= $isEdit ? 'Edit your post' : 'What are you making?' ?></h1>
    <p class="mt-3 text-lg opacity-80">The finished piece, the messy middle. It all belongs here.</p>

    <?php if (isset($errors['form'])): ?>
        <div class="alert alert-error mt-6" role="alert"><span><?= e($errors['form']) ?></span></div>
    <?php endif; ?>
    <?php if ($errors && !isset($errors['form'])): ?>
        <div class="alert alert-error mt-6" role="alert"><span>Please look at the red messages below. Your pictures have to be chosen again.</span></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="mt-8 space-y-7" id="post-form" data-post-form data-max-bytes="<?= ImageUploader::fileLimitBytes() ?>" data-max-steps="<?= PostController::MAX_STEP_PHOTOS ?>" novalidate>
        <?= csrf_field() ?>

        <?php // The finished artwork is the cover photo. ?>
        <div>
            <label class="group relative flex min-h-64 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-box border-2 border-dashed border-base-300 bg-base-300/50 p-6 text-center transition hover:border-primary" id="dropzone">
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" data-photo<?= field_attrs($errors, 'photo') ?>>
                <img src="<?= $cover ? e(ImageUploader::url($cover['FileName'], true)) : '' ?>" alt="Preview of your photo" class="absolute inset-0 h-full w-full object-cover <?= $cover ? '' : 'hidden' ?>" data-photo-preview>
                <span class="relative z-10 <?= $cover ? 'hidden' : '' ?>" data-photo-empty>
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-base-100"><?= icon('upload', 26) ?></span>
                    <span class="mt-3 block text-lg font-bold">Add your photo</span>
                    <span class="mt-1 block text-sm opacity-80">One finished piece. JPG, PNG or WEBP, up to <?= e($fileLimit) ?>.</span>
                </span>
            </label>
            <?= field_error($errors, 'photo') ?>
            <?php if ($isEdit): ?><p class="mt-2 text-sm opacity-80">Choose a new photo only if you want to replace this one.</p><?php endif; ?>
            <p class="mt-2 text-sm opacity-80">Only pictures of work you made yourself, by hand. No AI-generated images.</p>
        </div>

        <?php // Optional step photos show how the artwork was made. ?>
        <div>
            <?php if ($steps): ?>
                <p class="mb-2 text-sm font-bold">Your step photos <span class="font-normal opacity-80">(tick the ones you want to remove)</span></p>
                <div class="mb-4 flex flex-wrap gap-3">
                    <?php foreach ($steps as $s): ?>
                        <label class="relative cursor-pointer">
                            <input type="checkbox" name="remove[]" value="<?= (int)$s['ImageID'] ?>" class="peer sr-only" <?= in_array((int)$s['ImageID'], $old['remove'], true) ? 'checked' : '' ?>>
                            <img src="<?= e(ImageUploader::url($s['FileName'], true)) ?>" alt="Step photo <?= (int)$s['Position'] - 1 ?>" class="h-20 w-20 rounded-field border-2 border-transparent object-cover peer-checked:opacity-30 peer-focus-visible:outline peer-focus-visible:outline-2">
                            <span class="absolute inset-0 hidden place-items-center text-sm font-bold peer-checked:grid">Remove</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <label class="btn btn-outline w-full rounded-box border-base-300 bg-base-100 font-bold normal-case">
                <?= icon('plus', 18) ?> Add step photos (optional, up to <?= PostController::MAX_STEP_PHOTOS ?>)
                <input type="file" name="steps[]" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" data-steps<?= field_attrs($errors, 'steps') ?>>
            </label>
            <?= field_error($errors, 'steps') ?>
            <div class="mt-3 hidden flex-wrap gap-2" data-steps-list></div>
        </div>

        <label class="block">
            <span class="mb-1 block text-lg font-bold">Give it a title</span>
            <input class="input input-bordered input-lg w-full <?= field_class($errors, 'title') ?>" name="title" maxlength="100" placeholder="A little something I made…" value="<?= e($old['title'] === 'Untitled draft' ? '' : $old['title']) ?>" data-f="title" <?= field_attrs($errors, 'title') ?>>
            <?= field_error($errors, 'title') ?>
        </label>

        <div>
            <label for="description" class="mb-1 block text-lg font-bold">Tell us the story</label>
            <div class="rounded-field border border-base-300 bg-base-100 focus-within:border-primary">
                <div class="flex items-center gap-1 border-b border-base-300 p-2" role="toolbar" aria-label="Text styling">
                    <button type="button" class="btn btn-ghost btn-sm btn-square font-bold" data-wrap="**" data-target="description" aria-label="Bold">B</button>
                    <button type="button" class="btn btn-ghost btn-sm btn-square italic" data-wrap="*" data-target="description" aria-label="Italic">I</button>
                    <button type="button" class="btn btn-ghost btn-sm btn-square" data-insert="https://" data-target="description" aria-label="Add a link"><?= icon('link', 16) ?></button>
                </div>
                <textarea class="textarea w-full resize-y border-0 bg-transparent focus:outline-none <?= field_class($errors, 'description') ?>" rows="6" name="description" id="description" maxlength="5000" placeholder="How did it come together? Which yarn, clay or paint? Credit the pattern if you used one. Links are welcome." data-f="description"<?= field_attrs($errors, 'description') ?>><?= e($old['description']) ?></textarea>
            </div>
            <?= field_error($errors, 'description') ?>
            <p class="mt-1 text-sm opacity-80">Links are allowed. Readers get a “do you trust this link?” warning before they leave.</p>
        </div>

        <fieldset data-cats data-max="<?= PostController::MAX_CATEGORIES ?>">
            <legend class="mb-1 text-lg font-bold">Crafts <span class="text-sm font-normal opacity-80">(pick 1 to <?= PostController::MAX_CATEGORIES ?>)</span></legend>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($categories as $c): ?>
                    <label class="cursor-pointer"><input type="checkbox" class="peer sr-only" name="categories[]" value="<?= (int)$c['CategoryID'] ?>" <?= in_array((int)$c['CategoryID'], $old['categories'], true) ? 'checked' : '' ?>>
                        <span class="badge badge-lg badge-outline border-base-300 transition peer-checked:border-secondary peer-checked:bg-secondary/20 peer-focus-visible:outline peer-disabled:opacity-40"><?= e($c['CategoryName']) ?></span></label>
                <?php endforeach; ?>
            </div>
            <?= field_error($errors, 'categories') ?>
            <p class="mt-2 text-sm opacity-80" data-cats-hint>Where should people find this? Up to <?= PostController::MAX_CATEGORIES ?>.</p>
        </fieldset>

        <div>
            <span class="mb-1 block text-lg font-bold">Tags <span class="text-sm font-normal opacity-80">(help people find it in search)</span></span>
            <div class="flex flex-wrap items-center gap-2 rounded-field border border-base-300 bg-base-100 p-2 focus-within:border-primary" data-tags>
                <?php foreach ($old['tags'] as $t): ?><span class="badge badge-lg gap-1 bg-base-300 border-0" data-tag="<?= e($t) ?>">#<?= e($t) ?> <button type="button" aria-label="Remove tag <?= e($t) ?>" data-tag-x><?= icon('x', 12) ?></button></span><?php endforeach; ?>
                <input class="min-w-32 flex-1 bg-transparent p-1 outline-none" placeholder="Add a few helpful tags" aria-label="Add a tag" data-tag-input maxlength="30">
            </div>
            <input type="hidden" name="tags" value="<?= e(implode(',', $old['tags'])) ?>" data-tags-value>
            <p class="mt-1 text-sm opacity-80">Press Enter or comma after each tag. Up to <?= PostController::MAX_TAGS ?>.</p>
        </div>

        <label class="block"><span class="mb-1 block text-lg font-bold">Image description (alt text)</span>
            <textarea class="textarea textarea-bordered w-full <?= field_class($errors, 'alt') ?>" rows="2" name="alt" maxlength="200" placeholder="Describe your piece for someone who can’t see the photo." <?= field_attrs($errors, 'alt') ?>><?= e($old['alt']) ?></textarea>
            <?= field_error($errors, 'alt') ?></label>

        <?php if ($challenge !== null): ?>
            <label class="flex cursor-pointer items-start gap-3 rounded-box border border-base-300 bg-base-100 p-4">
                <input type="checkbox" class="checkbox checkbox-primary mt-0.5" name="challenge" value="1" <?= $old['challenge'] ? 'checked' : '' ?>>
                <span><span class="block font-bold">Enter this week’s challenge <span class="font-normal opacity-80">(optional)</span></span><span class="text-sm opacity-80"><?= e($challenge['ChallengeName']) ?></span></span>
            </label>
        <?php endif; ?>

        <div>
            <label class="flex cursor-pointer items-start gap-3 rounded-box border border-accent/40 bg-accent/10 p-4">
                <input type="checkbox" class="checkbox checkbox-primary mt-0.5" name="human_made" value="1" data-pledge <?= $old['humanMade'] ? 'checked' : '' ?><?= field_attrs($errors, 'human_made') ?>>
                <span><span class="block font-bold">I only share work I made myself, by hand. No AI.</span><span class="text-sm opacity-80">Required before you can post.</span></span>
            </label>
            <?= field_error($errors, 'human_made') ?>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <button type="button" class="btn btn-lg rounded-full sm:min-w-40" data-preview><?= icon('eye', 18) ?> Preview</button>
            <?php // The server checks the checkbox even when JavaScript is unavailable. ?>
            <button type="submit" name="action" value="publish" class="btn btn-primary btn-lg rounded-full sm:min-w-40" data-post-btn><?= $published ? 'Save changes' : 'Post' ?> <?= icon('arrow', 18) ?></button>
            <?php if (!$published): ?>
                <button type="submit" name="action" value="draft" class="btn btn-ghost btn-lg rounded-full">Save as draft</button>
            <?php endif; ?>
            <a href="<?= e($isEdit ? url('post', ['id' => $edit['PostID']]) : url('home')) ?>" class="btn btn-ghost btn-lg rounded-full sm:ml-auto">Cancel</a>
        </div>
        <?php if (!$published): ?>
            <p class="text-sm opacity-80">A draft is saved under <a class="link" href="<?= e(url('members')) ?>">My account</a>. Only you can see it.</p>
        <?php endif; ?>
    </form>
</section>

<?php // The preview shows the post like a card in the feed. ?>
<dialog id="modal-preview" class="modal">
    <div class="modal-box max-w-xl">
        <h3 class="text-xl font-semibold">Preview</h3>
        <p class="text-sm opacity-80">This is how your post will look on the feed.</p>
        <article class="mt-4 rounded-box bg-base-100 p-4">
            <header class="flex items-center gap-3"><?= avatar($user, 40) ?><div class="min-w-0 flex-1"><p class="font-bold"><?= e($user['Username']) ?></p><p class="text-sm opacity-80">just now</p></div><span class="flex flex-wrap justify-end gap-1" data-pv="category"></span></header>
            <p class="mt-3 leading-relaxed" data-pv="description"></p>
            <img class="mt-3 max-h-80 w-full object-cover" src="" alt="" data-pv-img>
            <h4 class="mt-3 font-display text-xl font-semibold" data-pv="title"></h4>
            <p class="mt-1 flex flex-wrap gap-2" data-pv="tags"></p>
        </article>
        <div class="modal-action">
            <button type="button" class="btn rounded-full" data-close>Back to editing</button>
            <?php if (!$published): ?><button type="button" class="btn btn-ghost rounded-full" data-submit-as="draft">Save as draft</button><?php endif; ?>
            <button type="button" class="btn btn-primary rounded-full" data-submit-as="publish"><?= $published ? 'Save changes' : 'Post' ?></button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button aria-label="Close">close</button></form>
</dialog>
