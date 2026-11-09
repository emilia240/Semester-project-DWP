<?php /* The bottom of every HTML page: the two little windows, the toast, the JavaScript file and the closing tags. */ ?>
<?php partial('modals'); ?>
<div class="toast toast-bottom toast-center z-[90] mb-16 hidden md:mb-4" id="toast" role="status" aria-live="polite"><div class="alert rounded-full bg-neutral px-5 text-neutral-content shadow-lg"><span id="toast-text"></span></div></div>
<script src="assets/js/app.js" defer></script>
</body>
</html>
