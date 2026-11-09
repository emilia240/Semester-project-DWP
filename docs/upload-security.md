# How picture upload works, and how it is protected

Files: `classes/ImageUploader.php`, `classes/ImageResizer.php`, `classes/UploadException.php`,
`controllers/PostController.php`, `models/PostModel.php`, `uploads/.htaccess`, `tests/upload_attacks.php`.

## The one idea

A file from a visitor is a **stranger's parcel**. We never keep the parcel. We check it, open the picture inside, and save a
**new picture that PHP made, with a name that PHP made**. The saved JPEG is created from the decoded pixels rather than
copied from the original file. These checks reduce risks, but no upload system can promise perfect safety.

## The way one picture travels

1. The form is sent (`enctype="multipart/form-data"`). PHP puts the file in a temporary folder and describes it in `$_FILES`.
2. `PostController` checks the CSRF token, then the text fields and **how many** pictures there are (before opening any).
3. `ImageUploader::store()` checks one file:
   1. PHP's error code (too big for php.ini, half sent ...)
   2. `is_uploaded_file()`: it really came through HTTP, nobody pointed us at `/etc/passwd`
   3. `filesize()`: at most 5 MB (or the php.ini limit if that is smaller)
   4. `finfo`: checks the file contents instead of trusting the name or browser's type. If fileinfo is unavailable, the code uses `getimagesize()` instead. Only JPEG, PNG and WEBP are accepted
   5. `ImageResizer::load()`: reads width and height from the header with `getimagesize()` BEFORE decoding. Longest side at most 6000 px, at most 24 megapixels, enough memory left. Only then does `imagecreatefrom...()` open it
   6. If EXIF support is available, the usual camera rotations are applied. The picture is drawn on a NEW white canvas with a longest side of at most 1600 px, and saved as JPEG with a random name (`bin2hex(random_bytes(16))` + `.jpg`). A small copy up to 600 px wide is saved as well (`..._t.jpg`) for the feed
4. If everything went fine, the database rows are written in ONE transaction (post, images, categories, tags).
5. If saving fails after files were written, the controller attempts to delete those files. Deletion failures are not currently reported.

## Attacks and the defence

| Attack | Defence | Where |
|---|---|---|
| A small file that is a gigantic picture (a 40000 x 40000 PNG is a few KB) | Size is read from the header first; limits on side and megapixels; memory check; all before decoding | `ImageResizer::load()` |
| A 50 MB file | `filesize()` limit; php.ini `upload_max_filesize` | `ImageUploader::store()`, `.user.ini` |
| Too many step photos | The controller checks for at most 6 step photos among the files PHP received; PHP also has a `max_file_uploads` setting | `PostController::validate()` |
| A form bigger than `post_max_size` (PHP then empties `$_POST` and `$_FILES`) | Detected from `CONTENT_LENGTH`, clear message | `ImageUploader::requestTooBig()` |
| A non-image file named like a picture, or claiming the type `image/jpeg` | `finfo` checks the content, not the name or browser's type; the picture must also be decoded | `ImageUploader::sniffMime()`, `ImageResizer::load()` |
| A real picture with PHP hidden in a comment or after the end | The new file is drawn from the pixels only, so nothing else is copied (tested) | `ImageResizer::save()` |
| `../../index.php` as file name | The visitor's file name is never used. Names are random hex; `delete()` only accepts `^[a-f0-9]{32}\.jpg$` | `ImageUploader` |
| Running something in `uploads/` | The uploader saves re-encoded JPEGs, and `.htaccess` denies names that do not end in `.jpg` when Apache is configured to read it | `ImageUploader`, `uploads/.htaccess` |
| SVG or HTML with a script | Not on the allowed list (SVG can contain scripts) | `ImageUploader::store()` |
| EXIF (GPS position of the photo) | Not copied to the new file | `ImageResizer` |
| Forged form (no CSRF token) | After checking whether the request is too large, `csrf_check()` checks the token before saving | `PostController` |
| Editing or deleting somebody else's post by typing the address | Owner (or admin for delete) checked in PHP; buttons being hidden is not protection | `PostController` |
| Seeing a draft by typing its address | A draft gives 404 to everybody except its owner | `PostController::show()` |
| SQL injection | PDO prepared statements, all SQL in `PostModel` | `PostModel` |
| XSS in title or description | `e()` on everything; `format_text()` escapes FIRST and only then adds bold, italic, links | `includes/view.php` |
| Deleting files while a post still needs them | The delete flow removes the database row first, then attempts to remove the files | `PostController::delete()` |

## Limits (change them in ONE place each)

| Limit | Value | Constant |
|---|---|---|
| File size | 5 MB | `ImageUploader::MAX_FILE_BYTES` |
| Longest side of an upload | 6000 px | `ImageResizer::MAX_SIDE` |
| Megapixels | 24 | `ImageResizer::MAX_PIXELS` |
| Saved size | 1600 px / 600 px small | `ImageUploader::FULL_SIDE`, `THUMB_WIDTH` |
| Step photos | 6 (+ cover) | `PostController::MAX_STEP_PHOTOS` |
| Crafts per post | 3 | `PostController::MAX_CATEGORIES` |

## Honest limits of this solution

- Animated GIFs are not accepted (not needed: the design says JPG, PNG, WEBP).
- Everything is saved as JPEG, so a transparent PNG gets a white background.
- Mirrored EXIF orientations (rare) are not corrected; the normal 90 / 180 degree turns are.
- WEBP decoding and EXIF rotation depend on the corresponding PHP extensions being available.
- There is no limit on how many posts one member can make per hour yet (rate limiting is a possible next step).
- On a host with a low `memory_limit`, a very large picture is refused with a polite message instead of being processed.
- File cleanup currently suppresses deletion errors, so a failed cleanup can leave unused files on disk.

## How to run the attack test

    php tests/upload_attacks.php

It builds the test files itself in a temporary folder, tries each one, and prints PASS or FAIL. It does not use the site's
database or uploads folder. In our Windows test, PHP showed warnings when it could not open two PHP-code samples. The script
still reported PASS for those samples because they were refused, so those two results do not prove content detection worked.
Security software blocked a separate diagnostic containing similar PHP text, but the reason for the original warnings has
not been confirmed. Do not disable antivirus protection to run the test.

## Questions Kim may ask, and short answers

- **"What if I upload a 20000 x 20000 picture?"** `getimagesize()` reads only the header, so we see 20000 x 20000 before decoding and refuse. GD needs width x height x 4 bytes to open a picture, so decoding it would use about 1.6 GB.
- **"Why not just check the extension?"** The name is typed by the visitor, so it proves nothing. `finfo` looks at the first bytes of the file, and then we re-draw the picture anyway.
- **"Why save it again instead of moving the file?"** `move_uploaded_file()` would keep everything that is inside the file, including a hidden PHP script. Re-drawing from the pixels keeps only the picture.
- **"Where is the file name stored?"** Only our random name is stored in `PostImage.FileName`. The visitor's name is thrown away.
- **"What happens if the database fails after the files were saved?"** The controller attempts to delete the files written in this request (`PostController::save()`). A failed file deletion is not currently reported.
- **"What happens if the picture file is gone but the row exists?"** The picture cannot be shown. The post deletion flow removes the row first and then the files, but files can still go missing for other reasons.
- **"Why two classes?"** Separation of concerns: `ImageResizer` only knows pictures (open, make smaller, save). `ImageUploader` only knows uploads (is this file acceptable, where does it go). Neither knows about posts or HTML.
- **"Where is the class from the lecture?"** `ImageResizer` is the lecture's `Resizer` (load, resize, save) with the safety checks added; `fitInside()` replaces `resizeToWidth/Height/scale`, because we always want to keep the shape of the picture.
