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

        // On a short page 14 doodles would sit on top of each other, so every second one stays hidden.
        var short = decor.offsetHeight < 2400;
        items.forEach(function (item, index) {
            if (short && index % 2 === 1) { item.classList.add('hidden'); return; }
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
    /* Join step 2: update the hint and button as crafts are selected.
       The server still checks the choices, and the form works without JavaScript. */
    var craftForm = $('[data-min-select]');
    if (craftForm) {
        var craftButton = $('[data-needs-select]', craftForm);
        var craftBoxes = $$('input[name="crafts[]"]', craftForm);
        var craftHint = $('[data-hint]', craftForm);
        var updateCrafts = function () {
            var count = craftBoxes.filter(function (box) { return box.checked; }).length;
            craftButton.disabled = count < 1;
            craftHint.textContent = count < 1 ? 'Choose at least one to continue.' : count + ' picked. You can change this later.';
        };
        craftBoxes.forEach(function (box) { box.addEventListener('change', updateCrafts); });
        updateCrafts();
    }

    /* Join step 3: enable the button after the checkbox is ticked.
       The server checks the agreement again when the form is submitted. */
    var guidelinesForm = $('[data-require-check]');
    if (guidelinesForm) {
        var agreeButton = $('[data-needs-check]', guidelinesForm);
        var agreeBox = $('[data-pledge]', guidelinesForm);
        var updateAgree = function () { agreeButton.disabled = !agreeBox.checked; };
        agreeBox.addEventListener('change', updateAgree);
        updateAgree();
    }

    /* ---------- small toast message at the bottom ---------- */
    var toastTimer;
    function toast(text) {
        var box = $('#toast'); if (!box) { return; }
        $('#toast-text').textContent = text;
        box.classList.remove('hidden');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { box.classList.add('hidden'); }, 2600);
    }

    /* ---------- "do you trust this link?": any link with data-ext opens the warning first ---------- */
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-ext]');
        if (!a) { return; }
        e.preventDefault();
        $('#ext-url').textContent = a.href;
        $('#ext-go').href = a.href;
        $('#modal-ext').showModal();
    });


    /* ---------- confirm before deleting: data-confirm="Title" data-confirm-text="..." [data-confirm-form="formId"] ---------- */
    var pendingForm = null;
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-confirm]');
        if (!b) { return; }
        var dialog = $('#modal-confirm');
        if (!dialog || typeof dialog.showModal !== 'function') { return; }
        // Keep the normal confirmation link as a fallback when JavaScript cannot show a dialog.
        e.preventDefault();
        $('#confirm-title').textContent = b.getAttribute('data-confirm');
        $('#confirm-text').textContent = b.getAttribute('data-confirm-text') || '';
        pendingForm = b.getAttribute('data-confirm-form');
        if (document.activeElement && document.activeElement.blur) { document.activeElement.blur(); }
        dialog.showModal();
    });
    var yes = $('#confirm-yes');
    if (yes) {
        yes.addEventListener('click', function () {
            $('#modal-confirm').close();
            if (pendingForm) { document.getElementById(pendingForm).requestSubmit(); pendingForm = null; }
        });
    }


    /* ---------- Report a comment ----------
       JavaScript opens the dialog; without it the same link opens the report page. */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-report]');
        if (!b) { return; }
        var dialog = $('#modal-report');
        if (!dialog || typeof dialog.showModal !== 'function') { return; }
        e.preventDefault();
        var form = $('form', dialog);
        form.reset();
        $('#report-id').value = b.getAttribute('data-report');
        if (document.activeElement && document.activeElement.blur) { document.activeElement.blur(); }
        dialog.showModal();
    });

    /* ---------- the heart for members: send the form without reloading ----------
       The normal form still works without JavaScript. While waiting, prevent a double click. */
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('[data-like-form]');
        if (!form || !window.fetch) { return; }
        e.preventDefault();
        var button = form.querySelector('button');
        if (button.disabled) { return; }
        button.disabled = true;
        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (r) {
            var type = r.headers.get('Content-Type') || '';
            if (type.indexOf('application/json') === -1) { throw new Error('not json'); }
            return r.json().then(function (data) { return { ok: r.ok, status: r.status, data: data }; });
        }).then(function (res) {
            // These errors happen before the like is changed, so the member can try again.
            if (!res.ok && (res.status === 403 || res.status === 404)) {
                toast(res.data.error || 'Could not like this post. Please try again.');
                button.disabled = false;
                return;
            }
            // A broken answer cannot tell us whether the server saved the change.
            if (!res.ok || !res.data || res.data.ok !== true || typeof res.data.liked !== 'boolean'
                || (res.data.count !== null && (!Number.isInteger(res.data.count) || res.data.count < 0))) {
                throw new Error('unexpected like answer');
            }
            button.setAttribute('aria-pressed', res.data.liked ? 'true' : 'false');
            var number = button.querySelector('[data-like-count]');
            if (number && res.data.count !== null) { number.textContent = res.data.count; }
            button.disabled = false;
        }).catch(function () {
            // Never resend: the first request may have succeeded even if its answer was lost.
            var message = document.createElement('span');
            message.setAttribute('role', 'alert');
            form.appendChild(message);
            message.textContent = 'Could not confirm the like. Refresh to check, and log in again if needed.';
        });
    });

    /* ---------- the heart for visitors: open the join window instead of going straight to login ---------- */
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-like-guest]');
        var dialog = $('#modal-join');
        if (!link || !dialog || !dialog.showModal) { return; }  // without a dialog the link still works
        e.preventDefault();
        var login = $('#join-login');
        if (login) { login.href = link.href; }                  // keep the post to return to after login
        dialog.showModal();
    });

    /* ---------- copy link (data-copy="relative url") ---------- */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-copy]');
        if (!b) { return; }
        var full = new URL(b.getAttribute('data-copy'), location.href).href;
        // Only say it worked after the browser confirms it could write to the clipboard.
        if (!navigator.clipboard) { toast('Could not copy the link. Please try again.'); return; }
        navigator.clipboard.writeText(full).then(function () {
            toast('Link copied.');
        }).catch(function () {
            toast('Could not copy the link. Please try again.');
        });
    });

    /* ---------- Grid / Single view on the front page ----------
       "single" puts a class on <html>; the CSS (.view-single .tbh-feed) then makes the feed one wide column.
       The choice is only a convenience, so it is kept in this browser (localStorage), and the page works without it. */
    var viewGroup = $('[data-view-group]');
    if (viewGroup) {
        var viewButtons = $$('[data-view]', viewGroup);
        var setView = function (view) {
            document.documentElement.classList.toggle('view-single', view === 'single');
            viewButtons.forEach(function (button) {
                var on = button.getAttribute('data-view') === view;
                button.setAttribute('aria-pressed', on ? 'true' : 'false');
                button.classList.toggle('btn-neutral', on);
            });
        };
        var savedView = 'grid';
        try { savedView = localStorage.getItem('tbh-view') === 'single' ? 'single' : 'grid'; } catch (err) { /* storage blocked: start with the grid */ }
        setView(savedView);
        viewGroup.classList.remove('hidden');
        viewButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var view = button.getAttribute('data-view');
                setView(view);
                try { localStorage.setItem('tbh-view', view); } catch (err) { /* ignore */ }
            });
        });
    }

    /* ---------- Visitor hero: change pictures, with a way to stop the movement ----------
       Play overrides the current hover and focus until each one leaves the card.
       A new hover or focus then stops the pictures again. Reduced motion is always respected. */
    var hero = $('[data-hero]');
    if (hero) {
        var slides = $$('[data-slide]', hero), shown = 0, paused = false;
        var ignoreHover = false, ignoreFocus = false, heroTimer;
        var pauseButton = $('[data-hero-pause]', hero);
        var heroStep = parseInt(hero.getAttribute('data-interval'), 10) || 1500;
        var lessMotion = function () {
            return document.documentElement.classList.contains('reduce-motion') || matchMedia('(prefers-reduced-motion: reduce)').matches;
        };
        var nextHeroSlide = function () {
            slides[shown].classList.remove('is-active'); slides[shown].setAttribute('aria-hidden', 'true');
            shown = (shown + 1) % slides.length;
            slides[shown].classList.add('is-active'); slides[shown].removeAttribute('aria-hidden');
        };
        var startHeroTimer = function () {
            // Restart the timer after Play, so two pictures cannot change almost at once.
            clearInterval(heroTimer);
            heroTimer = setInterval(function () {
                if (paused || (hero.matches(':hover') && !ignoreHover)
                    || (hero.contains(document.activeElement) && !ignoreFocus)
                    || document.hidden || lessMotion()) { return; }
                nextHeroSlide();
            }, heroStep);
        };
        if (slides.length > 1) {
            startHeroTimer();
            hero.addEventListener('mouseleave', function () { ignoreHover = false; });
            hero.addEventListener('focusout', function (event) {
                if (!hero.contains(event.relatedTarget)) { ignoreFocus = false; }
            });
        }
        if (pauseButton) {
            pauseButton.hidden = false;
            pauseButton.addEventListener('click', function () {
                paused = !paused;
                pauseButton.setAttribute('aria-pressed', paused ? 'true' : 'false');
                pauseButton.setAttribute('aria-label', paused ? 'Play the pictures' : 'Pause the pictures');
                $('[data-hero-pause-icon]', pauseButton).hidden = paused;
                $('[data-hero-play-icon]', pauseButton).hidden = !paused;
                if (!paused) {
                    // This explicit Play choice applies to the hover and focus already on the card.
                    ignoreHover = hero.matches(':hover');
                    ignoreFocus = hero.contains(document.activeElement);
                    if (!document.hidden && !lessMotion()) { nextHeroSlide(); }
                    startHeroTimer();
                }
            });
        }
    }

    /* ---------- Maker card: the newest comments fade in one after another ----------
       The comments rest while the whole card is hovered or focused, while the tab is hidden,
       or when less motion is requested. Play overrides the current hover and focus until they leave the card. */
    $$('[data-comments]').forEach(function (box) {
        var card = box.closest('[data-maker-card]');
        var items = $$('[data-comment]', box), now = 0, stopped = false, ignoreHover = false, ignoreFocus = false, timer;
        var button = $('[data-comments-pause]', box);
        var interval = parseInt(box.getAttribute('data-interval'), 10) || 5000;
        if (items.length < 2) { return; }
        var calm = function () {
            return document.documentElement.classList.contains('reduce-motion') || matchMedia('(prefers-reduced-motion: reduce)').matches;
        };
        var nextComment = function () {
            items[now].classList.remove('is-active'); items[now].setAttribute('aria-hidden', 'true');
            now = (now + 1) % items.length;
            items[now].classList.add('is-active'); items[now].removeAttribute('aria-hidden');
        };
        var startTimer = function () {
            // Restart after Play so the next comment does not follow too soon.
            clearInterval(timer);
            timer = setInterval(function () {
                if (stopped || (card.matches(':hover') && !ignoreHover) || (card.contains(document.activeElement) && !ignoreFocus)
                    || document.hidden || calm()) { return; }
                nextComment();
            }, interval);
        };
        startTimer();
        card.addEventListener('mouseleave', function () { ignoreHover = false; });
        card.addEventListener('focusout', function (event) { if (!card.contains(event.relatedTarget)) { ignoreFocus = false; } });
        if (button) {
            button.hidden = false;
            button.addEventListener('click', function () {
                stopped = !stopped;
                button.setAttribute('aria-pressed', stopped ? 'true' : 'false');
                button.setAttribute('aria-label', stopped ? 'Play the comments' : 'Pause the comments');
                $('[data-comments-pause-icon]', button).hidden = stopped;
                $('[data-comments-play-icon]', button).hidden = !stopped;
                if (!stopped) {
                    // Play also works while the button keeps focus or the mouse stays on the card.
                    ignoreHover = card.matches(':hover');
                    ignoreFocus = card.contains(document.activeElement);
                    if (!document.hidden && !calm()) { nextComment(); }
                    startTimer();
                }
            });
        }
    });

    /* ---------- post page: click a step photo to show it large ---------- */
    $$('[data-thumb]').forEach(function (t) {
        t.addEventListener('click', function () {
            $('#post-main').src = t.getAttribute('data-thumb');
            $$('[data-thumb]').forEach(function (o) { o.classList.replace('border-primary', 'border-transparent'); });
            t.classList.replace('border-transparent', 'border-primary');
        });
    });



    /* ---------- dialogs: a button with data-close closes the dialog it sits in ---------- */
    document.addEventListener('click', function (e) {
        var closer = e.target.closest('[data-close]');
        if (closer) { var d = closer.closest('dialog'); if (d) { d.close(); } }
    });

    /* ---------- text box helpers: B / I wrap the selected text, the link button adds https:// ---------- */
    function wrap(textarea, mark) {
        var s = textarea.selectionStart, e = textarea.selectionEnd, v = textarea.value;
        // A double click often selects the space after the word too. The marks must touch the words, or they stay visible as * on the page.
        while (s < e && /\s/.test(v.charAt(s))) { s++; }
        while (e > s && /\s/.test(v.charAt(e - 1))) { e--; }
        textarea.value = v.slice(0, s) + mark + v.slice(s, e) + mark + v.slice(e);
        textarea.focus();
        textarea.setSelectionRange(s + mark.length, e + mark.length);
    }
    document.addEventListener('click', function (e) {
        var w = e.target.closest('[data-wrap]');
        if (w) {
            var ta = w.getAttribute('data-target') ? document.getElementById(w.getAttribute('data-target')) : $('textarea', w.closest('form'));
            wrap(ta, w.getAttribute('data-wrap'));
            return;
        }
        var ins = e.target.closest('[data-insert]');
        if (ins) {
            var t2 = document.getElementById(ins.getAttribute('data-target'));
            t2.value += (t2.value && !/\s$/.test(t2.value) ? ' ' : '') + ins.getAttribute('data-insert'); t2.focus();
        }
    });

    /* ---------- Back after saving a post ----------
       The post page gets this marker only after PHP has saved successfully. sessionStorage belongs to this tab. */
    var savedPostForm = $('[data-saved-post-form]');
    if (savedPostForm) {
        try {
            sessionStorage.setItem('tbhSavedPostForm', savedPostForm.getAttribute('data-saved-post-form'));
        } catch (err) {
            console.warn('Back shortcut unavailable: this browser blocked session storage.');
        }
        // Remove the one-use marker from the address, so it is not included when the address is copied.
        var cleanPostUrl = new URL(location.href);
        cleanPostUrl.searchParams.delete('saved');
        history.replaceState(history.state, '', cleanPostUrl.href);
    }

    /* ---------- CREATE POST form ---------- */
    var pf = $('[data-post-form]');
    if (pf) {
        // Skip this form on Back only when the post page confirmed that this form was saved.
        var formKey = pf.getAttribute('data-form-key');
        var leaveTo = pf.getAttribute('data-leave-to');
        var navigation = performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
        function leaveSavedForm(persisted) {
            var cameBack = persisted || (navigation && navigation.type === 'back_forward');
            try {
                if (sessionStorage.getItem('tbhSavedPostForm') !== formKey) { return; }
                sessionStorage.removeItem('tbhSavedPostForm');
            } catch (err) {
                console.warn('Back shortcut unavailable: this browser blocked session storage.');
                return;
            }
            if (cameBack) { location.replace(leaveTo); }
        }
        leaveSavedForm(false);
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) { leaveSavedForm(true); }
        });

        var maxBytes = parseInt(pf.getAttribute('data-max-bytes'), 10) || 5 * 1024 * 1024;
        var maxSteps = parseInt(pf.getAttribute('data-max-steps'), 10) || 6;
        var photo = $('[data-photo]', pf), pimg = $('[data-photo-preview]', pf), pempty = $('[data-photo-empty]', pf);
        photo.addEventListener('change', function () {
            var f = photo.files[0]; if (!f) { return; }
            if (f.size > maxBytes) { toast('That photo is bigger than ' + Math.round(maxBytes / 1048576) + ' MB. Try a smaller one.'); photo.value = ''; return; }
            pimg.src = URL.createObjectURL(f); pimg.classList.remove('hidden'); pempty.classList.add('hidden');
        });
        var steps = $('[data-steps]', pf), list = $('[data-steps-list]', pf);
        steps.addEventListener('change', function () {
            list.innerHTML = '';
            if (steps.files.length > maxSteps) { toast('At most ' + maxSteps + ' step photos, please.'); steps.value = ''; list.classList.add('hidden'); return; }
            var tooBig = Array.prototype.some.call(steps.files, function (f) { return f.size > maxBytes; });
            if (tooBig) { toast('A step photo is bigger than ' + Math.round(maxBytes / 1048576) + ' MB. Try a smaller one.'); steps.value = ''; list.classList.add('hidden'); return; }
            Array.prototype.forEach.call(steps.files, function (f) {
                var i = document.createElement('img'); i.src = URL.createObjectURL(f); i.alt = ''; i.className = 'h-16 w-16 rounded-field object-cover'; list.appendChild(i);
            });
            list.classList.toggle('hidden', !steps.files.length); list.classList.toggle('flex', !!steps.files.length);
        });

        // the Post button only works after the human-made box is ticked
        var pledge = $('[data-pledge]', pf), post = $('[data-post-btn]', pf);
        var sync = function () { post.disabled = !pledge.checked; };
        pledge.addEventListener('change', sync); sync();

        // tags: Enter or comma adds a tag, the little x removes it, suggestions add with one click
        var tagsBox = $('[data-tags]', pf), tagIn = $('[data-tag-input]', pf), tagVal = $('[data-tags-value]', pf);
        function tagList() { return $$('[data-tag]', tagsBox).map(function (t) { return t.getAttribute('data-tag'); }); }
        function saveTags() { tagVal.value = tagList().join(','); }
        function addTag(raw) {
            var t = raw.toLowerCase().replace(/^#/, '').replace(/[^a-z0-9\-_æøå]/g, '').slice(0, 30);
            if (!t || tagList().indexOf(t) !== -1 || tagList().length >= 8) { return; }
            var s = document.createElement('span'); s.className = 'badge badge-lg gap-1 bg-base-300 border-0'; s.setAttribute('data-tag', t);
            // Text nodes keep a member's tag as text, not HTML the browser could execute.
            s.textContent = '#' + t + ' ';
            var remove = document.createElement('button');
            remove.type = 'button'; remove.setAttribute('aria-label', 'Remove tag ' + t);
            remove.setAttribute('data-tag-x', ''); remove.textContent = '×';
            s.appendChild(remove);
            tagsBox.insertBefore(s, tagIn); saveTags();
        }
        tagIn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(tagIn.value); tagIn.value = ''; }
            if (e.key === 'Backspace' && !tagIn.value) { var l = $$('[data-tag]', tagsBox).pop(); if (l) { l.remove(); saveTags(); } }
        });
        tagIn.addEventListener('blur', function () { if (tagIn.value) { addTag(tagIn.value); tagIn.value = ''; } });
        pf.addEventListener('click', function (e) {
            var x = e.target.closest('[data-tag-x]'); if (x) { x.parentElement.remove(); saveTags(); }
            var a = e.target.closest('[data-tag-add]'); if (a) { addTag(a.getAttribute('data-tag-add')); }
        });

        // categories: at most 3 (the rest grey out)
        var catBox = $('[data-cats]', pf);
        if (catBox) {
            var max = parseInt(catBox.getAttribute('data-max'), 10) || 3, cats = $$('input[type=checkbox]', catBox), chint = $('[data-cats-hint]', catBox);
            var syncCats = function () {
                var n = cats.filter(function (c) { return c.checked; }).length;
                cats.forEach(function (c) { c.disabled = !c.checked && n >= max; });
                chint.textContent = n >= max ? 'That is the maximum (' + max + '). Untick one to pick another.' : (n ? n + ' of ' + max + ' picked.' : 'Where should people find this? Up to ' + max + '.');
            };
            cats.forEach(function (c) { c.addEventListener('change', syncCats); }); syncCats();
        }

        // preview: copy what is typed into the feed-card preview
        $('[data-preview]', pf).addEventListener('click', function () {
            if (!pf.reportValidity()) { return; }
            if (catBox && !$$('input:checked', catBox).length) { toast('Pick at least one category.'); catBox.scrollIntoView({ block: 'center', behavior: 'smooth' }); return; }
            var d = $('#modal-preview');
            // Build formatting elements ourselves. Member text always stays in text nodes.
            var description = $('[data-pv=description]', d);
            var text = pf.elements.description.value || pf.elements.title.value;
            // Match the same bold and italic markers as format_text() in PHP, including text across lines.
            var pattern = /\*\*\*([\s\S]+?)\*\*\*|\*\*([\s\S]+?)\*\*|(?<![\w*])\*(?!\s)([\s\S]+?)\*(?![\w*])|#([a-z0-9_-]+)/gi;
            var match, previous = 0;
            description.replaceChildren();
            while ((match = pattern.exec(text)) !== null) {
                description.appendChild(document.createTextNode(text.slice(previous, match.index)));
                var part = document.createElement(match[1] || match[2] ? 'strong' : (match[3] ? 'em' : 'span'));
                if (match[1]) {
                    // Nest italic inside bold when the text has three stars on each side.
                    var italic = document.createElement('em');
                    italic.textContent = match[1];
                    part.appendChild(italic);
                } else {
                    if (match[4]) { part.className = 'font-bold text-primary'; }
                    part.textContent = match[2] || match[3] || '#' + match[4];
                }
                description.appendChild(part);
                previous = pattern.lastIndex;
            }
            description.appendChild(document.createTextNode(text.slice(previous)));
            $('[data-pv=title]', d).textContent = pf.elements.title.value;
            var categoryPreview = $('[data-pv=category]', d);
            categoryPreview.replaceChildren();
            $$('input:checked', catBox).forEach(function (c) {
                var badge = document.createElement('span');
                badge.className = 'badge badge-ghost'; badge.textContent = c.parentElement.textContent.trim();
                categoryPreview.appendChild(badge);
            });
            var tagPreview = $('[data-pv=tags]', d);
            tagPreview.replaceChildren();
            tagList().forEach(function (t) {
                var tag = document.createElement('span');
                tag.className = 'font-bold text-primary'; tag.textContent = '#' + t;
                if (tagPreview.childNodes.length) { tagPreview.appendChild(document.createTextNode(' ')); }
                tagPreview.appendChild(tag);
            });
            var im = $('[data-pv-img]', d); im.src = pimg.src || ''; im.alt = pf.elements.alt.value;
            d.showModal();
        });
        $$('[data-submit-as]').forEach(function (b) {
            b.addEventListener('click', function () {
                var real = pf.querySelector('button[name=action][value=' + b.getAttribute('data-submit-as') + ']');
                if (b.getAttribute('data-submit-as') === 'publish' && !pledge.checked) { $('#modal-preview').close(); pledge.focus(); toast('Please tick the human-made box first.'); return; }
                pf.requestSubmit(real);
            });
        });
    }


})();
