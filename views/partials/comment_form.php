<?php
/*
The box to write a comment, or a reply (when $parent is more than 0). Members only.
Needs: $postId, $parent, $text to refill, and $focus for the reply box.
Without JavaScript it is a normal form. app.js provides the B / I / link helpers.
*/
$parent = (int)($parent ?? 0);
$boxId  = $parent > 0 ? 'reply-body-' . $parent : 'comment-body';
?>
<form method="post" action="<?= e(url('add-comment')) ?>" class="tbh-card <?= $parent > 0 ? 'mt-3' : 'mt-4' ?> p-4"<?= $parent > 0 ? '' : ' id="comment-form"' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="post" value="<?= (int)$postId ?>">
    <input type="hidden" name="parent" value="<?= $parent ?>">
    <div class="mb-2 flex items-center gap-1" role="toolbar" aria-label="Text styling">
        <button type="button" class="btn btn-ghost btn-sm btn-square font-bold" data-wrap="**" data-target="<?= $boxId ?>" aria-label="Bold">B</button>
        <button type="button" class="btn btn-ghost btn-sm btn-square italic" data-wrap="*" data-target="<?= $boxId ?>" aria-label="Italic">I</button>
        <button type="button" class="btn btn-ghost btn-sm btn-square" data-insert="https://" data-target="<?= $boxId ?>" aria-label="Add a link"><?= icon('link', 16) ?></button>
    </div>
    <label for="<?= $boxId ?>" class="sr-only"><?= $parent > 0 ? 'Your reply' : 'Your comment' ?></label>
    <textarea class="textarea w-full" rows="3" name="body" id="<?= $boxId ?>" required maxlength="<?= CommentController::MAX_LENGTH ?>"<?= !empty($focus) ? ' autofocus' : '' ?>
        placeholder="<?= $parent > 0 ? 'Write a reply…' : 'Ask about the technique, or tell them what’s working.' ?>"><?= e($text ?? '') ?></textarea>
    <div class="mt-3 flex items-center justify-end gap-2">
        <?php if ($parent > 0): ?><a href="<?= e(url('post', ['id' => $postId]) . '#comment-' . $parent) ?>" class="btn btn-ghost btn-sm rounded-full">Cancel</a><?php endif; ?>
        <button type="submit" class="btn btn-primary btn-sm rounded-full"><?= $parent > 0 ? 'Send reply' : 'Post comment' ?></button>
    </div>
</form>
