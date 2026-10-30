<?php
/*
icons.php  =  small inline SVG pictures, so we need no icon library (and no extra request).
icon('arrow')                 normal 24x24 line icons for buttons and menus
craft_icon('brush', 90)       big hand-drawn tools used only as quiet decoration (views/partials/decor.php)
Both return HTML, so they are printed with  <?= icon('arrow') ?>  (no e() needed: no user text inside).
*/

function icon(string $name, int $size = 20, string $class = ''): string
{
    static $paths = [
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'back' => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'x' => '<path d="M18 6L6 18M6 6l12 12"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'star' => '<path d="M12 2l3.1 6.3 6.9 1-5 4.8 1.2 6.9L12 17.8 5.8 21l1.2-6.9-5-4.8 6.9-1z"/>',
        'bookmark' => '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>',
        'pencil' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
    ];
    $inner = $paths[$name] ?? '';
    return '<svg class="shrink-0 ' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/* Little line drawings of craft tools. Drawn on a 64 x 64 grid, colour = currentColor. */
function craft_icon(string $name, int $size = 80, string $class = ''): string
{
    $d = [
        'brush'   => '<rect x="28" y="3" width="8" height="30" rx="4"/><path d="M27 33h10v8H27z"/><path d="M27 41c0 8-4 12-4 19 6-3 12-3 18 0 0-7-4-11-4-19"/>',
        'scissors' => '<circle cx="17" cy="48" r="8"/><circle cx="47" cy="48" r="8"/><path d="M22 42L44 6M42 42L20 6"/>',
        'pencil'  => '<path d="M10 54l4-13L43 12a4 4 0 0 1 6 0l3 3a4 4 0 0 1 0 6L23 50z"/><path d="M38 17l9 9M14 41l9 9"/>',
        'yarn'    => '<circle cx="30" cy="32" r="21"/><path d="M11 23c13 7 29 7 42-2M9 37c14 7 31 7 44-2M17 14c11 10 13 27 5 40M34 11c10 10 12 27 4 41"/><path d="M49 47c9 4 11 11 4 14"/>',
        'button'  => '<circle cx="32" cy="32" r="23"/><circle cx="32" cy="32" r="15"/><circle cx="26" cy="26" r="2"/><circle cx="38" cy="26" r="2"/><circle cx="26" cy="38" r="2"/><circle cx="38" cy="38" r="2"/>',
        'leaf'    => '<path d="M10 54C10 27 27 9 55 9c0 27-17 45-45 45z"/><path d="M10 54l28-28M27 41h13M33 33v-12"/>',
        'needle'  => '<path d="M9 56L51 8"/><ellipse cx="51" cy="8" rx="2.2" ry="5.5" transform="rotate(41 51 8)"/><path d="M47 14c9 8-6 12 2 20s-8 12-2 21"/>',
        'spoon'   => '<ellipse cx="32" cy="15" rx="11" ry="13"/><path d="M28 28h8l-1 30a3 3 0 0 1-6 0z"/>',
        'hook'    => '<path d="M10 56L45 21"/><path d="M45 21c-4-7 4-14 11-9"/><path d="M6 60l9-9"/>',
        'palette' => '<path d="M32 7C18 7 7 17 7 30s11 25 23 25c7 0 7-5 4-8s-1-9 5-9h8c7 0 11-4 11-10C58 15 47 7 32 7z"/><circle cx="21" cy="28" r="3"/><circle cx="31" cy="18" r="3"/><circle cx="44" cy="22" r="3"/>',
        'ruler'   => '<rect x="5" y="21" width="54" height="22" rx="3"/><path d="M14 21v9M22 21v6M30 21v9M38 21v6M46 21v9M54 21v6"/>',
        'rings'   => '<circle cx="32" cy="32" r="24"/><circle cx="32" cy="32" r="16"/><circle cx="32" cy="32" r="8"/><path d="M32 8v5M32 51v5M8 32h5M51 32h5"/>',
        'hoop'    => '<circle cx="32" cy="34" r="22"/><circle cx="32" cy="34" r="17"/><path d="M28 12V6h8v6"/><path d="M24 34c4-8 12-8 16 0-4 8-12 8-16 0z"/>',
        'thimble' => '<path d="M14 54c0-18 2-36 18-36s18 18 18 36z"/><path d="M12 54h40M20 30h4M30 26h4M40 30h4M22 40h4M32 36h4M42 40h4"/>',
        'pin'     => '<circle cx="32" cy="20" r="11"/><path d="M32 31v27"/>',
        'star'    => '<path d="M32 6l7.5 17.5L58 25l-14 12.5L48 56 32 46 16 56l4-18.5L6 25l18.5-1.5z"/>',
    ];
    return '<svg class="shrink-0 ' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 64 64" fill="none" stroke="currentColor" '
        . 'stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($d[$name] ?? '') . '</svg>';
}
