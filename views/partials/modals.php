<?php
/*
The little windows (dialogs) that any page can open. They are filled in by assets/js/app.js:
  data-confirm="Title" data-confirm-text="..." data-confirm-form="formId"   -> "Are you sure?" (then it sends that form)
  a link with data-ext                                                        -> "Do you trust this link?"
  a link with data-report="commentId"                                         -> "Report this comment"
*/
?>
<dialog id="modal-ext" class="modal">
    <div class="modal-box">
        <h3 class="text-xl font-semibold">Do you trust this link?</h3>
        <p class="py-3">You are about to leave To Be Human. We do not check other websites, so only go on if you trust the person who wrote it.</p>
        <p class="break-all rounded-field bg-base-200 p-3 text-sm" id="ext-url"></p>
        <div class="modal-action">
            <form method="dialog"><button class="btn rounded-full">Stay here</button></form>
            <a id="ext-go" class="btn btn-primary rounded-full" href="#" target="_blank" rel="nofollow noopener noreferrer">Open link</a>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button aria-label="Close">close</button></form>
</dialog>

<?php // The report dialog posts to the same controller as the fallback page. ?>
<?php if (logged_in()): ?>
<dialog id="modal-report" class="modal">
    <div class="modal-box">
        <h3 class="text-xl font-semibold">Report this comment</h3>
        <p class="pt-2 text-sm opacity-80">Thank you for looking out for the community. A moderator will take a look.</p>
        <form method="post" action="<?= e(url('report-comment')) ?>" class="mt-4 space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="" id="report-id">
            <?php foreach (ReportController::REASONS as $key => $label): ?>
                <label class="flex items-center gap-3"><input type="radio" name="reason" value="<?= e($key) ?>" class="radio radio-primary radio-sm" required> <?= e($label) ?></label>
            <?php endforeach; ?>
            <textarea class="textarea w-full" rows="2" name="details" maxlength="<?= ReportController::MAX_DETAILS ?>" aria-label="Anything we should know? Needed for Something else." placeholder="Anything we should know? (needed for “Something else”)"></textarea>
            <div class="modal-action">
                <button type="button" class="btn rounded-full" data-close>Cancel</button>
                <button type="submit" class="btn btn-primary rounded-full">Send report</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button aria-label="Close">close</button></form>
</dialog>
<?php endif; ?>

<?php // Shown to a visitor who clicks a heart. assets/js/app.js opens it and gives "Log in" the right back= address. ?>
<dialog id="modal-join" class="modal">
    <div class="modal-box text-center">
        <p class="font-hand text-3xl text-accent">Want to join in?</p>
        <h3 class="mt-1 text-2xl font-semibold">Join To Be Human to like, save and comment</h3>
        <p class="py-3 opacity-80">It is free, there are no ads and everything here is made by hand.</p>
        <div class="flex flex-col gap-2 sm:flex-row sm:justify-center">
            <a href="<?= e(url('join')) ?>" class="btn btn-primary rounded-full">Join us</a>
            <a href="<?= e(url('login')) ?>" class="btn rounded-full" id="join-login">Log in</a>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button aria-label="Close">close</button></form>
</dialog>

<dialog id="modal-confirm" class="modal">
    <div class="modal-box">
        <h3 class="text-xl font-semibold" id="confirm-title">Are you sure?</h3>
        <p class="py-3" id="confirm-text"></p>
        <div class="modal-action">
            <form method="dialog"><button class="btn rounded-full">Keep it</button></form>
            <button type="button" class="btn btn-error rounded-full" id="confirm-yes">Yes, delete</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button aria-label="Close">close</button></form>
</dialog>
