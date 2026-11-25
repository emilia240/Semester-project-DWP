<?php
/*
The confirmation page for deleting a comment when JavaScript is unavailable.
Needs: $comment = the row from CommentModel::find(), including Username and ReplyCount.
*/
$replies = (int)$comment['ReplyCount'];
?>
<section class="mx-auto max-w-xl px-4 pb-16 pt-10">
    <h1 class="text-4xl font-semibold">Delete this comment?</h1>
    <blockquote class="mt-4 wrap-anywhere rounded-box border border-base-300 bg-base-200 p-4">
        <p class="text-sm font-bold"><?= e($comment['Username']) ?></p>
        <p class="mt-1 line-clamp-4"><?= e($comment['CommentText']) ?></p>
    </blockquote>
    <p class="mt-4">This cannot be undone.<?= $replies > 0 ? ' Its ' . $replies . ' ' . ($replies === 1 ? 'reply' : 'replies') . ' will be removed too.' : '' ?></p>

    <?php // Only the POST form can delete; the link merely returns to the comment. ?>
    <form method="post" action="<?= e(url('delete-comment')) ?>" class="mt-6 flex flex-wrap gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$comment['CommentID'] ?>">
        <a href="<?= e(url('post', ['id' => $comment['PostID']]) . '#comment-' . (int)$comment['CommentID']) ?>" class="btn rounded-full">Keep it</a>
        <button type="submit" class="btn btn-error rounded-full">Yes, delete</button>
    </form>
</section>
