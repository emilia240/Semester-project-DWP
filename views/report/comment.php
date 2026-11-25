<?php
/*
The report page works without JavaScript and displays errors after a redirect.
Needs: $comment, $reasons, $old (reason and details), $error and $back.
*/
?>
<section class="mx-auto max-w-xl px-4 pb-16 pt-10">
    <h1 class="text-4xl font-semibold">Report this comment</h1>
    <p class="mt-3 opacity-80">Thank you for looking out for the community. A moderator will take a look.</p>

    <blockquote class="mt-4 wrap-anywhere rounded-box border border-base-300 bg-base-200 p-4">
        <p class="text-sm font-bold">@<?= e($comment['Username']) ?></p>
        <p class="mt-1 line-clamp-4"><?= e($comment['CommentText']) ?></p>
    </blockquote>

    <?php if ($error !== null): ?>
        <div class="alert alert-error mt-4" role="alert"><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('report-comment')) ?>" class="mt-6 space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$comment['CommentID'] ?>">
        <fieldset class="space-y-3">
            <legend class="mb-1 font-bold">Why are you reporting it?</legend>
            <?php foreach ($reasons as $key => $label): ?>
                <label class="flex items-center gap-3"><input type="radio" name="reason" value="<?= e($key) ?>" class="radio radio-primary radio-sm" required <?= $old['reason'] === $key ? 'checked' : '' ?>> <?= e($label) ?></label>
            <?php endforeach; ?>
        </fieldset>
        <label class="block">
            <span class="mb-1 block text-sm font-bold">Anything we should know? <span class="font-normal opacity-80">(optional, needed for “Something else”)</span></span>
            <textarea class="textarea w-full" rows="3" name="details" maxlength="<?= ReportController::MAX_DETAILS ?>"><?= e($old['details']) ?></textarea>
        </label>
        <div class="flex flex-wrap justify-end gap-3 pt-2">
            <a href="<?= e($back) ?>" class="btn rounded-full">Cancel</a>
            <button type="submit" class="btn btn-primary rounded-full">Send report</button>
        </div>
    </form>
</section>
