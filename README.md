# To Be Human

A photo-sharing platform for **human-made arts and crafts**: no ads, no AI images, just people making things.
Semester project for PBA Web Development (PHP backend + Databases), SEA, 2026.

> Status: login, sign-up, e-mail confirmation, forgot/reset password, change password, account page and a
> placeholder admin area are done. Posts, boards and the rest of the sign-up steps come next.

## Run it on your own computer (XAMPP)

1. Put the folder in `htdocs` (for example `C:\xampp\htdocs\Semester-project-DWP`). Start **Apache** and **MySQL** in XAMPP.
2. phpMyAdmin -> **Import** -> `sql/01_schema.sql` (builds the database `dwp_semester`, tables Role, User, Token).
3. *Local only:* import `sql/02_test_users.sql` for two test users (admin and member). **Never import this file on the live server.**
4. Copy `includes/constants.example.php` to `includes/constants.php` and fill in your values.
   `constants.php` is in `.gitignore`, so passwords never reach GitHub.
5. Open `http://localhost/Semester-project-DWP/`.

With `MAIL_MODE` set to `'log'` no e-mail is sent: the confirmation and reset links are written to `logs/mail.log`.

### Changing the design (only needed if you edit the CSS or the classes in the views)

The CSS is built with Tailwind + daisyUI. The finished file `assets/css/app.css` is committed, so the site runs
without Node. If you change `assets-src/input.css` or add new classes in a view:

```
npm install        # once
npm run build      # makes assets/css/app.css
```

## How the code is organised (MVC with a front controller)

Every page is opened through **one** file, `index.php`, with a page name: `index.php?page=login`.

```
index.php            FRONT CONTROLLER: route table + the access check (guest / member / admin) in ONE place
controllers/         the "heavy lifters": read the form, validate, ask the models, choose a view
models/              everything that talks to the database (all SQL, prepared statements only)
views/               the HTML the visitor sees (no SQL, no logic beyond showing things)
includes/            helpers: session, CSRF, flash messages, validation, mail, the PDO connection
assets/              compiled CSS, fonts, two small scripts   (assets-src/ = the CSS source)
sql/                 schema and test data
```

A request travels like this:

```
browser -> index.php -> route table -> access check -> Controller -> Model -> database
                                                          |
                                                          +-> View -> HTML back to the browser
```

| Page (`?page=`) | Who | Controller::method |
|---|---|---|
| `home` | everybody | HomeController::index |
| `login`, `join`, `check-inbox`, `resend`, `forgot` | visitors only | AuthController |
| `verify`, `reset` | everybody (the link is the key) | AuthController |
| `logout` | POST + CSRF only | AuthController::logout |
| `members` (my account), `password` | logged-in members | AccountController |
| `admin` | role admin | AdminController::index |

`?page=` is only used as a **key** in the route table. It is never used as a file name, so a visitor cannot make the
site open a file or run a class that is not in the table.

## Security, in short

| Threat | What the code does |
|---|---|
| SQL injection | PDO **prepared statements** everywhere (in the models) |
| XSS | every value that came from a user goes through `e()` (htmlspecialchars) before it is printed |
| CSRF | a token in every form (`csrf_field()`), checked with `csrf_check()` on every POST, also logout |
| Weak passwords | `password_hash()` bcrypt; 8-72 characters with a letter and a number |
| Guessing passwords | lock-out after 5 wrong tries for 60 seconds (login and change password) |
| Finding out who has an account | login and forgot-password give the same answer for known and unknown e-mails, and a dummy hash keeps the timing the same |
| E-mail links | random 64-character token, only its SHA-256 hash is stored, single use, short expiry, one new e-mail per minute |
| Session theft | new session id at login and password change, idle timeout 2 h, cookie flags set in `includes/session.php` |
| Form tampering | everything is validated again in PHP; the HTML attributes are only a convenience |
| Error messages | `DEBUG` in `constants.php` is **false** on the live server (errors go to the log, not the screen) |
| Secrets on GitHub | `includes/constants.php` is in `.gitignore`; test users are local only |

### Content-Security-Policy (CSP)

`includes/session.php` sends this header on every page:

```
default-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'
```

It tells the browser to only run scripts, styles and images that come from our own site, and to refuse everything
else. So even if someone managed to get a `<script>` into a page (XSS), the browser would not run it.
The consequences for the code, and why there is no inline code anywhere:

- **No inline `<script>`**: all JavaScript is in `assets/js/` (`theme.js` is loaded in the head so the theme is
  set before the page is drawn, which avoids a flash).
- **No `style="..."` attributes** in the HTML: styling is done with classes. Where a value has to be dynamic
  (the avatar colour and size) the code picks from a fixed list of ready-made classes.
- **No `data:` images**: that is why `input.css` turns off daisyUI's noise texture (`--fx-noise: none`).
- Fonts are self-hosted (`assets/fonts/`), nothing is loaded from a CDN.

This is also *separation of concerns*: structure in the views, looks in the CSS, behaviour in the JavaScript.
