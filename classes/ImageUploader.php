<?php
/*
ImageUploader  =  takes ONE uploaded file and either refuses it (UploadException) or stores a safe version of it.

The rule behind everything: a file from a visitor is a stranger's parcel. We never keep the parcel itself.
We check it, open the picture, and save a NEW picture that PHP made, with a name that PHP made.

The checks, in order (the cheap ones first):
  1. did PHP receive it without an error?               (too big for php.ini, half sent, no temp folder ...)
  2. is it really an HTTP upload?                       (is_uploaded_file: stops someone pointing us at /etc/passwd)
  3. is it under the size limit?                        (we measure the real file with filesize(), we do not believe the browser)
  4. is it really a JPG, PNG or WEBP?                   (finfo reads the first bytes: the file NAME and the browser's "type" are ignored)
  5. is the picture itself reasonable?                  (ImageResizer: width, height, megapixels, memory, damaged file)
  6. save a big version and a small version as new JPEG files with a random name
Not trusted, never used: the file name from the visitor, the type from the browser, the size from the browser.
*/
class ImageUploader
{
    public const MAX_FILE_BYTES = 5 * 1024 * 1024;   // 5 MB per picture
    public const FULL_SIDE      = 1600;              // the picture on the post page: at most 1600 px on the long side
    public const THUMB_WIDTH    = 600;               // the small copy used in the feed
    public const JPEG_QUALITY   = 85;

    private string $folder;
    private bool $requireHttpUpload;

    /*
    $folder            where the pictures are saved (the uploads/ folder)
    $requireHttpUpload true on the real site. The tests in tests/ create files by hand, so they pass false.
    */
    public function __construct(string $folder, bool $requireHttpUpload = true)
    {
        $this->folder = rtrim($folder, '/\\');
        $this->requireHttpUpload = $requireHttpUpload;
    }

    // The biggest picture the server accepts: our limit, or php.ini's limit if that is smaller.
    public static function fileLimitBytes(): int
    {
        $ini = ImageResizer::iniBytes((string)ini_get('upload_max_filesize'));
        return $ini > 0 ? min(self::MAX_FILE_BYTES, $ini) : self::MAX_FILE_BYTES;
    }

    // The limit as words for a message: "5 MB".
    public static function fileLimitText(): string
    {
        return round(self::fileLimitBytes() / 1048576, 1) . ' MB';
    }

    // Did the whole form arrive? If the form is bigger than post_max_size, PHP throws away ALL of it ($_POST and $_FILES empty).
    public static function requestTooBig(): bool
    {
        $max = ImageResizer::iniBytes((string)ini_get('post_max_size'));
        return $max > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $max;
    }

    /*
    many() = turn $_FILES['steps'] (PHP's awkward "list of fields" shape) into a normal list of single files,
    and drop the empty slots (no file chosen). The CALLER checks how many there are, before anything is opened.
    */
    public static function many($files): array
    {
        if (!is_array($files) || !isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];                           // one single file
        }
        $list = [];
        foreach (array_keys($files['name']) as $i) {
            $one = [];
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
                $one[$key] = $files[$key][$i] ?? null;
            }
            if (($one['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
                $list[] = $one;
            }
        }
        return $list;
    }

    // Is there a file in this slot at all? (a visitor may leave the field empty)
    public static function chosen($file): bool
    {
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /*
    store() = check one uploaded file and save it. Returns the NEW file name (like  3f9a...c1.jpg).
    The small copy is saved next to it as  3f9a...c1_t.jpg
    */
    public function store($file): string
    {
        // ---- 1. did PHP get it? ----
        if (!is_array($file) || !isset($file['error']) || !is_int($file['error']) || !isset($file['tmp_name']) || !is_string($file['tmp_name'])) {
            throw new UploadException('The upload did not work. Please try again.');
        }
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new UploadException('That file is too big. The limit is ' . self::fileLimitText() . ' per picture.');
            case UPLOAD_ERR_PARTIAL:
                throw new UploadException('The file was only half sent. Please try again.');
            case UPLOAD_ERR_NO_FILE:
                throw new UploadException('No file was chosen.');
            default:                                   // no temp folder, cannot write to disk ...: our problem, not the visitor's
                throw new RuntimeException('Upload error code ' . $file['error']);
        }

        // ---- 2. a real upload? ----
        $tmp = $file['tmp_name'];
        if ($this->requireHttpUpload && !is_uploaded_file($tmp)) {
            throw new UploadException('The upload did not work. Please try again.');
        }

        // ---- 3. size ----
        $bytes = @filesize($tmp);
        if ($bytes === false || $bytes < 1) {
            throw new UploadException('That file is empty.');
        }
        if ($bytes > self::fileLimitBytes()) {
            throw new UploadException('That file is too big. The limit is ' . self::fileLimitText() . ' per picture.');
        }

        // ---- 4. what is it REALLY? finfo looks at the first bytes of the file, not at the name ----
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime = $this->sniffMime($tmp);
        if (!in_array($mime, $allowed, true)) {
            throw new UploadException('Only JPG, PNG and WEBP pictures are allowed.');
        }

        // ---- 5. is the picture itself reasonable? (this also checks width, height and memory) ----
        $resizer = new ImageResizer();
        $resizer->load($tmp);

        // ---- 6. save: a new name, always .jpg, a big and a small version ----
        $this->makeFolder();
        $base = bin2hex(random_bytes(16));             // 32 random characters: nobody can guess it, and it can never contain ../
        $name = $base . '.jpg';
        $thumb = $base . '_t.jpg';

        $resizer->fitInside(self::FULL_SIDE, self::FULL_SIDE);
        if (!$resizer->save($this->folder . '/' . $name, self::JPEG_QUALITY)) {
            throw new RuntimeException('Could not write ' . $name);
        }
        $resizer->resizeToWidth(self::THUMB_WIDTH);
        if (!$resizer->save($this->folder . '/' . $thumb, self::JPEG_QUALITY)) {
            @unlink($this->folder . '/' . $name);
            throw new RuntimeException('Could not write ' . $thumb);
        }
        @chmod($this->folder . '/' . $name, 0644);
        @chmod($this->folder . '/' . $thumb, 0644);

        return $name;
    }

    // Delete a picture and its small copy. Only names that look EXACTLY like ours are accepted, so a poisoned
    // name from the database (../../index.php) can never delete anything outside uploads/.
    public function delete(string $name): void
    {
        if (!preg_match('/^[a-f0-9]{32}\.jpg$/', $name)) {
            return;
        }
        @unlink($this->folder . '/' . $name);
        @unlink($this->folder . '/' . self::thumbName($name));
    }

    public function deleteMany(array $names): void
    {
        foreach ($names as $name) {
            $this->delete((string)$name);
        }
    }

    // 3f9a.jpg -> 3f9a_t.jpg
    public static function thumbName(string $name): string
    {
        return substr($name, 0, -4) . '_t.jpg';
    }

    // The web address of a stored picture (relative to index.php, like every other link on the site).
    public static function url(string $name, bool $thumb = false): string
    {
        return 'uploads/' . ($thumb ? self::thumbName($name) : $name);
    }

    // The real type of the file, read from its first bytes. Falls back to getimagesize() if the fileinfo extension is missing.
    private function sniffMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $path) : false;
            return is_string($mime) ? $mime : '';
        }
        $info = @getimagesize($path);
        return $info !== false ? (string)$info['mime'] : '';
    }

    private function makeFolder(): void
    {
        if (!is_dir($this->folder) && !@mkdir($this->folder, 0755, true) && !is_dir($this->folder)) {
            throw new RuntimeException('Could not create the uploads folder');
        }
        if (!is_writable($this->folder)) {
            throw new RuntimeException('The uploads folder is not writable');
        }
    }
}
