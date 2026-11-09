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
        $('#confirm-title').textContent = b.getAttribute('data-confirm');
        $('#confirm-text').textContent = b.getAttribute('data-confirm-text') || '';
        pendingForm = b.getAttribute('data-confirm-form');
        if (document.activeElement && document.activeElement.blur) { document.activeElement.blur(); }
        $('#modal-confirm').showModal();
    });
    var yes = $('#confirm-yes');
    if (yes) {
        yes.addEventListener('click', function () {
            $('#modal-confirm').close();
            if (pendingForm) { document.getElementById(pendingForm).requestSubmit(); pendingForm = null; }
        });
    }


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

    /* ---------- Back button (data-back): go back if there is somewhere to go back to ---------- */
    $$('[data-back]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            if (history.length > 1 && document.referrer.indexOf(location.host) !== -1) { e.preventDefault(); history.back(); }
        });
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

    /* ---------- CREATE POST form ---------- */
    var pf = $('[data-post-form]');
    if (pf) {
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
            var pattern = /\*\*(.+?)\*\*|\*(.+?)\*|#([a-z0-9_-]+)/gi;
            var match, previous = 0;
            description.replaceChildren();
            while ((match = pattern.exec(text)) !== null) {
                description.appendChild(document.createTextNode(text.slice(previous, match.index)));
                var part = document.createElement(match[1] ? 'strong' : (match[2] ? 'em' : 'span'));
                if (match[3]) { part.className = 'font-bold text-primary'; }
                part.textContent = match[1] || match[2] || '#' + match[3];
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
