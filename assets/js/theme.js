/*
theme.js  =  runs in the <head>, BEFORE the page is drawn.
It reads the visitor's saved choices (light/dark theme, text size, "reduce animations") and puts them on <html>.
If this waited until the end of the page, you would see a flash of the wrong theme.

Why is this a separate FILE and not a <script> inside the page?
The site sends a Content-Security-Policy (includes/session.php) that forbids inline scripts.
Scripts that are files on our own site are allowed.
*/
(function () {
    try {
        var html = document.documentElement;

        var theme = localStorage.getItem('tbh-theme');
        if (!theme) {
            theme = matchMedia('(prefers-color-scheme: dark)').matches ? 'tobehuman-dark' : 'tobehuman';
        }
        html.setAttribute('data-theme', theme);

        var textSize = localStorage.getItem('tbh-text');
        if (textSize) { html.style.setProperty('--tbh-text-size', textSize); }   // CSS-through-JavaScript is allowed by the policy

        if (localStorage.getItem('tbh-motion') === 'off') { html.classList.add('reduce-motion'); }
    } catch (e) {
        // localStorage can be blocked (private mode). Then we simply use the defaults.
    }
})();
