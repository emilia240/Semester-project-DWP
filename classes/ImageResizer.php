<?php
/*
ImageResizer  =  opens ONE picture, makes it smaller, saves it as a new JPEG.   (GD library)

It is the Resizer class from the OOP lecture (load, resize, save), made safe for pictures from strangers:
  - it looks at the size of the picture BEFORE it decodes it.
    A small file can hide a gigantic picture (a 20 000 x 20 000 pixel PNG of one colour is only a few KB).
    GD needs width x height x 4 bytes of memory to open it, so that picture would crash the server.
    getimagesize() reads only the header, so we can say "no" cheaply, before the memory is used.
  - it checks that there is enough memory left, and refuses politely if not
  - it only opens JPG, PNG and WEBP
  - it turns the picture the way the camera was held (EXIF orientation)
  - it draws the picture onto a NEW white canvas and saves that. The new file has none of the old file's
    extra data (GPS position, camera name, anything hidden inside), because only the pixels are copied.
*/
class ImageResizer
{
    // The longest side may be 6000 pixels and the whole picture 24 megapixels (a 12 MP phone photo is fine).
    public const MAX_SIDE   = 6000;
    public const MAX_PIXELS = 24000000;

    private ?GdImage $image = null;

    // Which picture types we can open: type number => name of the GD function that opens it.
    public static function decoders(): array
    {
        $decoders = [
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG  => 'imagecreatefrompng',
        ];
        if (function_exists('imagecreatefromwebp')) {
            $decoders[IMAGETYPE_WEBP] = 'imagecreatefromwebp';
        }
        return $decoders;
    }

    // "128M" -> bytes. PHP writes its limits like that in php.ini. -1 means "no limit".
    public static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $number = (int)$value;
        switch (strtoupper(substr($value, -1))) {
            case 'G':
                $number *= 1024;
                // no break: G is K x 1024 x 1024, so it falls through the next two lines
            case 'M':
                $number *= 1024;
                // no break
            case 'K':
                $number *= 1024;
        }
        return $number;
    }

    /*
    load() = check the picture, then open it.
    Throws UploadException (with a message for the visitor) when the picture is not acceptable.
    */
    public function load(string $file): void
    {
        $info = @getimagesize($file);                 // reads the header only: width, height, type
        if ($info === false) {
            throw new UploadException('That file is not a picture we can read.');
        }
        [$width, $height, $type] = $info;

        $decoders = self::decoders();
        if (!isset($decoders[$type])) {
            throw new UploadException('Only JPG, PNG and WEBP pictures are allowed.');
        }
        if ($width < 1 || $height < 1) {
            throw new UploadException('That picture has no size.');
        }
        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw new UploadException("That picture is {$width} x {$height} pixels. The longest side may be at most " . self::MAX_SIDE . ' pixels.');
        }
        if ($width * $height > self::MAX_PIXELS) {
            $megapixels = round($width * $height / 1000000, 1);
            throw new UploadException("That picture is {$megapixels} megapixels. The limit is " . (self::MAX_PIXELS / 1000000) . ' megapixels. Please make a smaller copy.');
        }
        // A phone photo can carry a note "turn me 90 degrees" (EXIF). Turning needs a second full-size copy in memory.
        $angle = $type === IMAGETYPE_JPEG ? $this->cameraAngle($file) : 0;
        $this->checkMemory($width, $height, $angle !== 0);

        $image = @$decoders[$type]($file);            // the @ hides the PHP warning for a broken file: we give our own message
        if ($image === false) {
            throw new UploadException('That picture is damaged and could not be opened.');
        }
        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            $image = $rotated === false ? $image : $rotated;
        }
        $this->image = $image;
    }

    // Is there enough memory to open this picture? Opening needs about width x height x 4 bytes (we measured: a 24 megapixel
    // picture uses about 100 MB). Turning it needs a second copy. This is a safety net: better a polite "too large" than a crashed page.
    private function checkMemory(int $width, int $height, bool $willRotate): void
    {
        $limit = self::iniBytes((string)ini_get('memory_limit'));
        if ($limit < 0) {
            return;
        }
        $needed = (int)($width * $height * 4 * ($willRotate ? 2.1 : 1.5)) + 16 * 1024 * 1024;
        if (memory_get_usage() + $needed > $limit) {
            throw new UploadException('That picture is too large for the server to handle. Please make a smaller copy.');
        }
    }

    // A phone stores the picture "sideways" and writes a note in the file (EXIF). Without turning it, the photo shows up rotated.
    // Returns how many degrees to turn it (0 = leave it). Only reads the notes, does not open the picture.
    private function cameraAngle(string $file): int
    {
        if (!function_exists('exif_read_data')) {
            return 0;
        }
        $exif = @exif_read_data($file);
        $orientation = is_array($exif) ? (int)($exif['Orientation'] ?? 1) : 1;
        return [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;   // imagerotate turns counter-clockwise
    }

    public function width(): int
    {
        return imagesx($this->image);
    }

    public function height(): int
    {
        return imagesy($this->image);
    }

    // Make the picture fit inside a box, keeping its shape. A picture that is already smaller stays the size it is.
    public function fitInside(int $maxWidth, int $maxHeight): void
    {
        $ratio = min(1, $maxWidth / $this->width(), $maxHeight / $this->height());
        $this->resize(max(1, (int)round($this->width() * $ratio)), max(1, (int)round($this->height() * $ratio)));
    }

    // Same, but only the width is given (used for the small version in the feed).
    public function resizeToWidth(int $width): void
    {
        if ($width < $this->width()) {
            $ratio = $width / $this->width();
            $this->resize($width, max(1, (int)round($this->height() * $ratio)));
        }
    }

    // Draw the picture onto a NEW white canvas of the wanted size. White, so a transparent PNG does not turn black in a JPEG.
    private function resize(int $width, int $height): void
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $this->image, 0, 0, 0, 0, $width, $height, $this->width(), $this->height());
        $this->image = $canvas;
    }

    // Save as a JPEG. Everything we keep is a JPEG made by us, never the file the visitor sent.
    public function save(string $path, int $quality = 85): bool
    {
        imageinterlace($this->image, true);           // progressive: shows a blurry picture first on slow connections
        return @imagejpeg($this->image, $path, $quality);
    }
}
