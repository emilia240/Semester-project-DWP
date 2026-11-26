<?php
/* Settings > Appearance. Needs nothing from the database: there is no form, and nothing is sent to the server.
   The three choices are saved in the browser of THIS device (localStorage) by assets/js/app.js:
       theme       -> 'tbh-theme'   (data-theme-choice)   light, dark, or "like my device" (= nothing saved)
       text size   -> 'tbh-text'    (data-text-size)
       animations  -> 'tbh-motion'  (data-motion-toggle)
   assets/js/theme.js puts the saved choices on the page before it is drawn, so there is no flash.
   The radio buttons start unchecked here: app.js ticks the one that is saved. */

// The three theme cards: the value that is saved, the text on the card and its icon.
$themes = [
    ['value' => 'tobehuman',      'label' => 'Light',          'icon' => 'sun'],
    ['value' => 'tobehuman-dark', 'label' => 'Dark',           'icon' => 'moon'],
    ['value' => '',               'label' => 'Like my device', 'icon' => 'settings'],
];

// The four text sizes: the percentage that is saved and the word on the button.
$textSizes = [
    ['value' => '90%',  'label' => 'Small'],
    ['value' => '100%', 'label' => 'Normal'],
    ['value' => '115%', 'label' => 'Large'],
    ['value' => '130%', 'label' => 'Extra large'],
];

partial('settings_open', ['active' => 'appearance']);
?>
    <div class="tbh-card space-y-8 p-6">
        <div>
            <h2 class="text-2xl font-semibold">Appearance</h2>
            <p class="mt-1 opacity-80">How To Be Human looks and feels on this device.</p>
        </div>

        <div class="space-y-3">
            <h3 class="font-display text-xl font-semibold">Theme</h3>
            <div class="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Theme">
                <?php foreach ($themes as $theme): ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="theme" class="peer sr-only" value="<?= e($theme['value']) ?>" data-theme-choice>
                        <span class="flex items-center gap-3 rounded-box border-2 border-base-content/30 bg-base-100 p-4 font-bold peer-checked:border-primary peer-checked:bg-primary/10 peer-focus-visible:outline"><?= icon($theme['icon'], 20) ?> <?= e($theme['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="space-y-5 border-t border-base-300 pt-6">
            <h3 class="font-display text-xl font-semibold">Accessibility</h3>

            <div>
                <p class="mb-2 font-bold">Text size</p>
                <div class="join" role="radiogroup" aria-label="Text size">
                    <?php foreach ($textSizes as $size): ?>
                        <input class="btn join-item" type="radio" name="textsize" aria-label="<?= e($size['label']) ?>" value="<?= e($size['value']) ?>" data-text-size>
                    <?php endforeach; ?>
                </div>
            </div>

            <label class="flex cursor-pointer items-center justify-between gap-4">
                <span>
                    <span class="block font-bold">Reduce animations</span>
                    <span class="text-sm opacity-80">Also follows the setting of your device.</span>
                </span>
                <input type="checkbox" class="toggle toggle-primary" data-motion-toggle>
            </label>
        </div>
    </div>
<?php partial('settings_close'); ?>
