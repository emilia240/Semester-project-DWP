<?php
/* The progress bar for join steps 2 and 3. $current marks the active step. */
?>
<ul class="steps w-full" aria-label="Joining: step <?= (int)$current ?> of 3">
    <li class="step step-primary">Sign up</li>
    <li class="step step-primary" <?= $current === 2 ? 'aria-current="step"' : '' ?>>Your crafts</li>
    <li class="step <?= $current >= 3 ? 'step-primary' : '' ?>" <?= $current === 3 ? 'aria-current="step"' : '' ?>>Guidelines</li>
</ul>
