<?php
/*
The messages that were saved with flash_set() before a redirect ("Account created!", "Wrong e-mail or password" ...).
flash_get_all() empties the list, so every message is shown exactly once.
Errors use role="alert" (read out at once), the others role="status" (read out politely).
*/
$classes = ['success' => 'alert-success', 'error' => 'alert-error', 'warning' => 'alert-warning', 'info' => 'alert-info'];
foreach (flash_get_all() as $f): ?>
    <div class="alert <?= e($classes[$f['type']] ?? 'alert-info') ?> mb-4" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
        <span><?= e($f['message']) ?></span>
    </div>
<?php endforeach; ?>
