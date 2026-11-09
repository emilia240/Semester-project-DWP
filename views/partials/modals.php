<?php
/*
The two little windows (dialogs) that any page can open. They are filled in by assets/js/app.js:
  data-confirm="Title" data-confirm-text="..." data-confirm-form="formId"   -> "Are you sure?" (then it sends that form)
  a link with data-ext                                                        -> "Do you trust this link?"
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
