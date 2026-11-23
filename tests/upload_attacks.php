<?php
/*
tests/upload_attacks.php  =  "what if a visitor sends us something nasty?"

Run it in a terminal from the project folder:      php tests/upload_attacks.php
It makes a pile of hostile files (huge pictures, fake pictures, PHP hidden inside a picture ...), gives each one
to ImageUploader exactly like a visitor's upload would, and checks that the answer is the safe one.
It writes its files in a temporary folder and deletes it again. It does not touch the database or the site.

PASS = the site did the safe thing.   FAIL = a hole. The script ends with exit code 1 if anything failed.
*/
require_once __DIR__ . '/../classes/UploadException.php';
require_once __DIR__ . '/../classes/ImageResizer.php';
require_once __DIR__ . '/../classes/ImageUploader.php';

ini_set('memory_limit', '1024M');                      // THIS script needs room to build the huge test pictures; the site is tested with a small limit below

$tmpRoot = sys_get_temp_dir() . '/tbh-upload-test-' . bin2hex(random_bytes(4));
$inbox   = $tmpRoot . '/inbox';                        // where the "visitor's" files are
$store   = $tmpRoot . '/uploads';                      // where ImageUploader saves
$canary  = $tmpRoot . '/canary.txt';                   // a file OUTSIDE uploads/, to prove nothing outside can be touched
mkdir($inbox, 0777, true);
file_put_contents($canary, 'still here');

$uploader = new ImageUploader($store, false);          // false = the files are made by hand, not by a real HTTP upload
$passed = 0;
$failed = 0;

function result(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    printf("  [%s] %s%s\n", $ok ? 'PASS' : 'FAIL', $label, $detail !== '' ? '  (' . $detail . ')' : '');
}

// Hand a file to the uploader the way PHP's $_FILES would. $claimedName is what the "browser" says the file is called.
function fileEntry(string $path, string $claimedName = 'photo.jpg', int $error = UPLOAD_ERR_OK): array
{
    return ['name' => $claimedName, 'type' => 'image/jpeg', 'tmp_name' => $path, 'error' => $error, 'size' => (int)@filesize($path)];
}

// Expect a refusal with a friendly message.
function expectRefused(string $label, array $entry, string $mustContain = ''): void
{
    global $uploader;
    try {
        $name = $uploader->store($entry);
        result($label, false, 'was ACCEPTED as ' . $name);
    } catch (UploadException $e) {
        $ok = $mustContain === '' || stripos($e->getMessage(), $mustContain) !== false;
        result($label, $ok, $e->getMessage());
    } catch (Throwable $e) {
        result($label, false, 'crashed with ' . get_class($e) . ': ' . $e->getMessage());
    }
}

// Expect it to be stored. Returns the stored file name, or null.
function expectAccepted(string $label, array $entry): ?string
{
    global $uploader;
    try {
        $name = $uploader->store($entry);
        result($label, true, $name);
        return $name;
    } catch (Throwable $e) {
        result($label, false, 'was refused: ' . $e->getMessage());
        return null;
    }
}

// ---- helpers that build hostile files ----

function makeJpeg(string $path, int $w, int $h, int $quality = 85, bool $noise = false): void
{
    $im = imagecreatetruecolor($w, $h);
    imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 200, 120, 60));
    imagefilledellipse($im, intdiv($w, 2), intdiv($h, 2), intdiv($w, 2), intdiv($h, 2), imagecolorallocate($im, 30, 90, 160));
    if ($noise) {
        for ($i = 0; $i < 400000; $i++) {
            imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1), random_int(0, 0xFFFFFF));
        }
    }
    imagejpeg($im, $path, $quality);
}

// A REAL png of one colour, written as a stream so this script itself does not need gigabytes: a few KB on disk, millions of pixels.
function makeHugeSolidPng(string $path, int $w, int $h): void
{
    $chunk = fn(string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    $ctx = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
    $row = str_repeat("\0", 1 + $w * 3);               // filter byte 0 + black pixels
    $z = '';
    for ($y = 0; $y < $h; $y++) {
        $z .= deflate_add($ctx, $row, ZLIB_NO_FLUSH);
    }
    $z .= deflate_add($ctx, '', ZLIB_FINISH);
    file_put_contents($path, "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0)) . $chunk('IDAT', $z) . $chunk('IEND', ''));
}

// Only the HEADER of a png that claims to be gigantic. There is no picture behind it at all.
function makeLyingPngHeader(string $path, int $w, int $h): void
{
    $chunk = fn(string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    file_put_contents($path, "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0)) . $chunk('IEND', ''));
}

// Take a small normal JPEG and rewrite the width and height in its header to 60000 x 60000.
function makeLyingJpegHeader(string $from, string $path): void
{
    $data = file_get_contents($from);
    $pos = strpos($data, "\xFF\xC0");                  // SOF0 marker: start of frame (holds height and width)
    $data = substr_replace($data, pack('nn', 60000, 60000), $pos + 5, 4);
    file_put_contents($path, $data);
}

// Add an EXIF note "Orientation = 6" (the phone was held sideways) right after the start of a JPEG.
function addExifOrientation(string $from, string $path, int $orientation): void
{
    $data = file_get_contents($from);
    $tiff = "MM\x00\x2A\x00\x00\x00\x08" . "\x00\x01" . pack('nnNnn', 0x0112, 3, 1, $orientation, 0) . "\x00\x00\x00\x00";
    $app1 = "\xFF\xE1" . pack('n', 2 + 6 + strlen($tiff)) . "Exif\x00\x00" . $tiff;
    file_put_contents($path, substr($data, 0, 2) . $app1 . substr($data, 2));
}

// ---------------------------------------------------------------------------------------------
echo "\nA. Normal pictures must work\n";
makeJpeg("$inbox/normal.jpg", 3000, 2000);
$name = expectAccepted('a normal 3000x2000 JPEG is accepted', fileEntry("$inbox/normal.jpg"));
if ($name) {
    [$w, $h] = getimagesize("$store/$name");
    result('...and was made smaller (longest side 1600)', max($w, $h) === ImageUploader::FULL_SIDE, "{$w}x{$h}");
    [$tw] = getimagesize("$store/" . ImageUploader::thumbName($name));
    result('...and a small copy (600 wide) was made too', $tw === ImageUploader::THUMB_WIDTH, "{$tw} wide");
    result('...saved under a random name, always .jpg', (bool)preg_match('/^[a-f0-9]{32}\.jpg$/', $name));
}
makeJpeg("$inbox/small.jpg", 300, 200);
$name = expectAccepted('a small 300x200 picture is accepted and NOT blown up', fileEntry("$inbox/small.jpg"));
if ($name) {
    [$w, $h] = getimagesize("$store/$name");
    result('...it stays 300x200', $w === 300 && $h === 200, "{$w}x{$h}");
}
$im = imagecreatetruecolor(400, 300);
imagealphablending($im, false);
imagesavealpha($im, true);
imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
imagepng($im, "$inbox/transparent.png");
$name = expectAccepted('a see-through PNG is accepted', fileEntry("$inbox/transparent.png", 'logo.png'));
if ($name) {
    $out = imagecreatefromjpeg("$store/$name");
    $rgb = imagecolorat($out, 5, 5);
    result('...and the see-through part became WHITE, not black', $rgb > 0xF0F0F0, sprintf('#%06X', $rgb));
}
if (isset(ImageResizer::decoders()[IMAGETYPE_WEBP])) {
    imagewebp(imagecreatetruecolor(500, 300), "$inbox/pic.webp");
    expectAccepted('a WEBP is accepted', fileEntry("$inbox/pic.webp", 'pic.webp'));
}

// ---------------------------------------------------------------------------------------------
echo "\nB. Kim's favourite: pictures that are far too big\n";
makeLyingPngHeader("$inbox/bomb1.png", 40000, 40000);
expectRefused('PNG header that claims 40000 x 40000 pixels (a few bytes on disk)', fileEntry("$inbox/bomb1.png", 'bomb.png'), 'pixels');
makeHugeSolidPng("$inbox/bomb2.png", 7000, 7000);
$kb = round(filesize("$inbox/bomb2.png") / 1024);
expectRefused("a REAL 7000 x 7000 PNG that is only {$kb} KB on disk (49 megapixels)", fileEntry("$inbox/bomb2.png", 'bomb.png'), 'pixels');
makeHugeSolidPng("$inbox/bomb3.png", 5500, 5500);
expectRefused('a real 5500 x 5500 PNG: under the side limit, but 30 megapixels', fileEntry("$inbox/bomb3.png", 'bomb.png'), 'megapixels');
makeLyingJpegHeader("$inbox/small.jpg", "$inbox/bomb4.jpg");
expectRefused('a small JPEG whose header was edited to say 60000 x 60000', fileEntry("$inbox/bomb4.jpg"), 'pixels');
makeJpeg("$inbox/tall.jpg", 100, 6500);
expectRefused('a thin picture 100 x 6500 (longest side too long)', fileEntry("$inbox/tall.jpg"), 'pixels');

makeJpeg("$inbox/big_ok.jpg", 5800, 4100, 60);
$t = microtime(true);
$name = expectAccepted('the biggest allowed picture (23.8 megapixels) works', fileEntry("$inbox/big_ok.jpg"));
printf("        took %.1f seconds, PHP peak memory %d MB\n", microtime(true) - $t, memory_get_peak_usage(true) / 1048576);

// ---------------------------------------------------------------------------------------------
echo "\nB2. Strange SHAPES (Kim's very tall picture): the shorter side at least 200 px, the longer side at most 2 times the shorter\n";
makeJpeg("$inbox/strip1.jpg", 60, 1600);
expectRefused('a thin strip 60 x 1600 (shorter side under 200 pixels)', fileEntry("$inbox/strip1.jpg"), 'shorter side');
makeJpeg("$inbox/strip2.jpg", 1, 6000);
expectRefused('a 1 pixel wide line 1 x 6000', fileEntry("$inbox/strip2.jpg"), 'shorter side');
makeJpeg("$inbox/strip3.jpg", 300, 1500);
expectRefused('a tall picture 300 x 1500 (5 times as tall as wide)', fileEntry("$inbox/strip3.jpg"), 'thin shape');
makeJpeg("$inbox/strip4.jpg", 1500, 300);
expectRefused('the same turned sideways, 1500 x 300 (the rule works both ways)', fileEntry("$inbox/strip4.jpg"), 'thin shape');
makeJpeg("$inbox/three1.jpg", 2400, 800);
expectRefused('a 3:1 panorama 2400 x 800 (too thin to look good)', fileEntry("$inbox/three1.jpg"), 'thin shape');
makeJpeg("$inbox/three2.jpg", 800, 2400);
expectRefused('a 1:3 tall picture 800 x 2400 (too thin to look good)', fileEntry("$inbox/three2.jpg"), 'thin shape');
makeJpeg("$inbox/strip5.jpg", 2000, 6000 + 1);
expectRefused('2000 x 6001 (too long by a hair)', fileEntry("$inbox/strip5.jpg"), 'pixels');
makeJpeg("$inbox/edge1.jpg", 199, 400);
expectRefused('199 x 400 (one pixel under the minimum)', fileEntry("$inbox/edge1.jpg"), 'shorter side');
makeJpeg("$inbox/edge2.jpg", 200, 200);
expectAccepted('200 x 200 (exactly the minimum) is fine', fileEntry("$inbox/edge2.jpg"));
makeJpeg("$inbox/edge3.jpg", 600, 1201);
expectRefused('600 x 1201 (a hair over 2 times)', fileEntry("$inbox/edge3.jpg"), 'thin shape');

// Normal shapes must NOT be refused, and must NOT be cut: only scaled, the shape stays the same.
foreach ([
    'a normal landscape photo 4000 x 3000'    => [4000, 3000],
    'a portrait photo 3000 x 4000'            => [3000, 4000],
    'a 16:9 picture 1920 x 1080'              => [1920, 1080],
    'a square picture 2000 x 2000'            => [2000, 2000],
    'a phone picture 9:16, 1080 x 1920'       => [1080, 1920],
    'a panorama exactly 2:1, 2400 x 1200'     => [2400, 1200],
    'a tall picture exactly 1:2, 1000 x 2000' => [1000, 2000],
] as $label => [$w0, $h0]) {
    makeJpeg("$inbox/shape.jpg", $w0, $h0);
    $name = expectAccepted($label, fileEntry("$inbox/shape.jpg"));
    if ($name !== null) {
        [$w, $h] = getimagesize("$store/$name");
        $same = abs(($w / $h) - ($w0 / $h0)) < 0.02;
        result('...and it was only scaled, not cut (same shape)', $same, "{$w0}x{$h0} became {$w}x{$h}");
    }
}

// ---------------------------------------------------------------------------------------------
echo "\nC. Files that are too heavy\n";
makeJpeg("$inbox/heavy.jpg", 4000, 4000, 100, true);
$mb = round(filesize("$inbox/heavy.jpg") / 1048576, 1);
expectRefused("a {$mb} MB JPEG (the limit is 5 MB)", fileEntry("$inbox/heavy.jpg"), 'too big');
file_put_contents("$inbox/junk.jpg", random_bytes(6 * 1024 * 1024));
expectRefused('6 MB of random bytes called junk.jpg', fileEntry("$inbox/junk.jpg"), 'too big');
file_put_contents("$inbox/empty.jpg", '');
expectRefused('an empty file', fileEntry("$inbox/empty.jpg"), 'empty');

// ---------------------------------------------------------------------------------------------
echo "\nD. Files that lie about what they are\n";
file_put_contents("$inbox/fake.jpg", "Hello, I am a text file");
expectRefused('a text file called fake.jpg', fileEntry("$inbox/fake.jpg"), 'allowed');
file_put_contents("$inbox/shell.php.jpg", "<?php system(\$_GET['cmd']); ?>");
expectRefused('PHP code called shell.php.jpg', fileEntry("$inbox/shell.php.jpg", 'shell.php.jpg'), 'allowed');
file_put_contents("$inbox/shell.php", "<?php system(\$_GET['cmd']); ?>");
expectRefused('PHP code called shell.php, claiming to be image/jpeg', fileEntry("$inbox/shell.php", 'shell.php'), 'allowed');
file_put_contents("$inbox/page.html", "<html><script>alert(document.cookie)</script></html>");
expectRefused('an HTML page with a script, called page.jpg', fileEntry("$inbox/page.html", 'page.jpg'), 'allowed');
file_put_contents("$inbox/evil.svg", '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
expectRefused('an SVG with a script inside', fileEntry("$inbox/evil.svg", 'evil.svg'), 'allowed');
imagegif(imagecreatetruecolor(50, 50), "$inbox/anim.gif");
expectRefused('a GIF (not on the allowed list)', fileEntry("$inbox/anim.gif", 'anim.gif'), 'allowed');
file_put_contents("$inbox/trunc.jpg", substr(file_get_contents("$inbox/normal.jpg"), 0, 900));
try {
    $n = $uploader->store(fileEntry("$inbox/trunc.jpg"));
    result('a JPEG cut off after 900 bytes does not crash the site', true, 'stored as ' . $n . ', repaired by re-encoding');
} catch (UploadException $e) {
    result('a JPEG cut off after 900 bytes does not crash the site', true, $e->getMessage());
} catch (Throwable $e) {
    result('a JPEG cut off after 900 bytes does not crash the site', false, $e->getMessage());
}

// ---------------------------------------------------------------------------------------------
echo "\nE. A real picture with something nasty hidden inside\n";
$good = file_get_contents("$inbox/normal.jpg");
$comment = "\xFF\xFE" . pack('n', 2 + strlen("<?php echo 'HACKED'; ?>")) . "<?php echo 'HACKED'; ?>";
file_put_contents("$inbox/poly.jpg", substr($good, 0, 2) . $comment . substr($good, 2) . "\n<?php system(\$_GET['c']); ?>");
$name = expectAccepted('a valid JPEG with PHP in its comment AND after its end is accepted as a picture...', fileEntry("$inbox/poly.jpg"));
if ($name) {
    $saved = file_get_contents("$store/$name") . file_get_contents("$store/" . ImageUploader::thumbName($name));
    result('...but the saved file contains NO PHP code (it was re-drawn from the pixels)', strpos($saved, '<?php') === false && strpos($saved, 'HACKED') === false);
}

// ---------------------------------------------------------------------------------------------
echo "\nF. The file name is never trusted\n";
$name = expectAccepted('a file called ../../evil.php (a valid picture inside)', fileEntry("$inbox/small.jpg", '../../evil.php'));
if ($name) {
    result('...saved with a random name inside uploads/', (bool)preg_match('/^[a-f0-9]{32}\.jpg$/', $name));
}
result('...and nothing was written outside uploads/', !file_exists("$tmpRoot/evil.php") && !file_exists("$tmpRoot/../evil.php"));
$stored = array_diff(scandir($store), ['.', '..']);
$badFiles = array_filter($stored, fn($f) => !preg_match('/^[a-f0-9]{32}(_t)?\.jpg$/', $f));
result('every file in uploads/ is a random-name .jpg (' . count($stored) . ' files)', count($badFiles) === 0, implode(', ', $badFiles));

$ds = new ImageUploader($store, false);
$ds->delete('../canary.txt');
$ds->delete('../../canary.txt');
$ds->delete('/etc/passwd');
$ds->delete($canary);
result('delete() with a poisoned name (../canary.txt) deletes nothing', file_exists($canary));

// ---------------------------------------------------------------------------------------------
echo "\nG. Rotation and metadata\n";
makeJpeg("$inbox/land.jpg", 400, 200);
addExifOrientation("$inbox/land.jpg", "$inbox/sideways.jpg", 6);
if (function_exists('exif_read_data')) {
    $name = expectAccepted('a photo the phone marked "held sideways"', fileEntry("$inbox/sideways.jpg"));
    if ($name) {
        [$w, $h] = getimagesize("$store/$name");
        result('...is turned the right way (400x200 becomes 200x400)', $w === 200 && $h === 400, "{$w}x{$h}");
        result('...and the saved file has no EXIF data at all', strpos(file_get_contents("$store/$name"), 'Exif') === false);
    }
} else {
    echo "  [SKIP] the exif extension is not installed on this computer\n";
}

// ---------------------------------------------------------------------------------------------
echo "\nH. What PHP tells us about the upload\n";
expectRefused('PHP error: file bigger than upload_max_filesize', fileEntry("$inbox/small.jpg", 'a.jpg', UPLOAD_ERR_INI_SIZE), 'too big');
expectRefused('PHP error: file only half sent', fileEntry("$inbox/small.jpg", 'a.jpg', UPLOAD_ERR_PARTIAL), 'half');
expectRefused('tmp_name sent as an array (photo[tmp_name][]=...)', ['name' => 'a', 'type' => 'x', 'tmp_name' => ['/etc/passwd'], 'error' => 0, 'size' => 1]);
expectRefused('error field missing', ['name' => 'a', 'tmp_name' => "$inbox/small.jpg"]);
$real = new ImageUploader($store, true);
try {
    $real->store(fileEntry("$inbox/small.jpg"));
    result('a file that was NOT really uploaded over HTTP is refused (is_uploaded_file)', false);
} catch (UploadException $e) {
    result('a file that was NOT really uploaded over HTTP is refused (is_uploaded_file)', true);
}
try {
    $real->store(fileEntry('/etc/passwd'));
    result('pointing tmp_name at /etc/passwd is refused', false);
} catch (UploadException $e) {
    result('pointing tmp_name at /etc/passwd is refused', true);
}

// ---------------------------------------------------------------------------------------------
echo "\nI. Several files at once (the step photos)\n";
$shape = ['name' => ['a.jpg', '', 'c.jpg'], 'type' => ['', '', ''], 'tmp_name' => ['/x', '', '/y'], 'error' => [0, UPLOAD_ERR_NO_FILE, 0], 'size' => [1, 0, 1]];
result('empty slots are dropped from the list', count(ImageUploader::many($shape)) === 2);
result('garbage instead of $_FILES gives an empty list, not a crash', ImageUploader::many('hello') === [] && ImageUploader::many(null) === []);

// ---------------------------------------------------------------------------------------------
echo "\nJ. The same big picture on a small server (memory_limit = 128M)\n";
$php = escapeshellarg(PHP_BINARY);
$script = $tmpRoot . '/lowmem.php';
// var_export makes valid PHP strings for Windows paths too: backslashes must not become escape characters.
$exceptionFile = var_export(__DIR__ . '/../classes/UploadException.php', true);
$resizerFile = var_export(__DIR__ . '/../classes/ImageResizer.php', true);
$uploaderFile = var_export(__DIR__ . '/../classes/ImageUploader.php', true);
$storePath = var_export($store, true);
$bigPicture = var_export($inbox . '/big_ok.jpg', true);
$normalPicture = var_export($inbox . '/normal.jpg', true);
file_put_contents($script, '<?php
ini_set("memory_limit", "128M");
require ' . $exceptionFile . '; require ' . $resizerFile . '; require ' . $uploaderFile . ';
$u = new ImageUploader(' . $storePath . ', false);
foreach ([' . $bigPicture . ', ' . $normalPicture . '] as $f) {
    try { $u->store(["name"=>"a.jpg","type"=>"","tmp_name"=>$f,"error"=>0,"size"=>1]); echo "ACCEPTED " . basename($f) . "\n"; }
    catch (UploadException $e) { echo "REFUSED  " . basename($f) . ": " . $e->getMessage() . "\n"; }
}');
$out = shell_exec("$php $script 2>&1");
echo preg_replace('/^/m', '        ', trim((string)$out)) . "\n";
result('with 128 MB the server refuses the large picture and accepts the normal one',
    strpos((string)$out, 'REFUSED  big_ok.jpg:') !== false
    && strpos((string)$out, 'ACCEPTED normal.jpg') !== false
    && strpos((string)$out, 'Fatal') === false);

// ---------------------------------------------------------------------------------------------
// clean up
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) {
    $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
}
rmdir($tmpRoot);

echo "\n" . ($failed === 0 ? "ALL $passed CHECKS PASSED" : "$failed CHECK(S) FAILED, $passed passed") . "\n\n";
exit($failed === 0 ? 0 : 1);
