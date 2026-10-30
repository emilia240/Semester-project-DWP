/*
app.js  =  the little bit of JavaScript the site needs. Plain JS, no library.
Loaded at the end of every page (defer). Each block says which data-attribute in the views switches it on.
Nothing here is a security measure: JavaScript can be switched off or changed by the visitor,
so every check that matters is repeated in PHP.
*/
(function () {
    'use strict';

    var $  = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    // localStorage can throw (private mode), so every use goes through these two.
    function saved(key)        { try { return localStorage.getItem(key); } catch (e) { return null; } }
    function save(key, value)  { try { localStorage.setItem(key, value); } catch (e) {} }

    /* ---------- dark / light button: <button data-theme-toggle> ---------- */
    $$('[data-theme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var html = document.documentElement;
            var next = html.getAttribute('data-theme') === 'tobehuman-dark' ? 'tobehuman' : 'tobehuman-dark';
            html.setAttribute('data-theme', next);
            save('tbh-theme', next);
        });
    });

    /* ---------- show / hide password: <button data-pw-toggle> next to <input data-pw> ---------- */
    $$('[data-pw-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = button.parentElement.querySelector('[data-pw]');
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });

    /* ---------- friendly check that the two new passwords match: <form data-validate> ----------
       This only saves the visitor a round trip. PHP checks it again (signup, reset and password pages). */
    $$('[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var first = form.elements.password, second = form.elements.password2;
            if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
            if (first && second && first.value !== second.value) {
                event.preventDefault();
                second.setCustomValidity('The two passwords are not the same.');
                second.reportValidity();
                second.addEventListener('input', function () { second.setCustomValidity(''); }, { once: true });
            }
        });
    });

    /* ---------- cookie notice, shown once: <div id="cookie-note"> ---------- */
    var note = $('#cookie-note');
    if (note && !saved('tbh-cookies')) {
        setTimeout(function () { note.classList.remove('hidden'); }, 800);
        $('[data-cookie-ok]', note).addEventListener('click', function () {
            save('tbh-cookies', 'ok');
            note.classList.add('hidden');
        });
    }

    /* ---------- craft doodles in the page margins: <div data-decor> (views/partials/decor.php) ----------
       The PHP file only says WHAT and roughly WHERE (data-side, data-x, data-top, data-rot, data-drift).
       This code puts each doodle in place and makes it slide a little against the scroll.
       Why not write style="left: ..." in the PHP? The Content-Security-Policy forbids inline style attributes.
       Setting styles from a script file is allowed. */
    var decor = $('[data-decor]');
    if (decor) {
        var items = $$('[data-drift]', decor);
        var reduceMotion = function () {
            return document.documentElement.classList.contains('reduce-motion') || matchMedia('(prefers-reduced-motion: reduce)').matches;
        };

        items.forEach(function (item) {
            item.style[item.getAttribute('data-side') === 'l' ? 'left' : 'right'] = item.getAttribute('data-x') + 'px';
            item.style.top = item.getAttribute('data-top') + '%';
            item.style.setProperty('--r', item.getAttribute('data-rot') + 'deg');
            item.classList.add('is-ready');
        });

        var waiting = false;
        var drift = function () {
            waiting = false;
            if (reduceMotion()) { return; }
            var top = decor.getBoundingClientRect().top, middle = window.innerHeight / 2;
            items.forEach(function (item) {
                var centre = top + item.offsetTop + item.offsetHeight / 2;
                item.style.setProperty('--y', Math.round((middle - centre) * parseFloat(item.getAttribute('data-drift'))) + 'px');
            });
        };
        window.addEventListener('scroll', function () {
            if (!waiting) { waiting = true; requestAnimationFrame(drift); }   // at most once per frame
        }, { passive: true });
        window.addEventListener('resize', drift);
        drift();
    }
})();
