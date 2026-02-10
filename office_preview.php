<?php
$file = urldecode($_GET['file'] ?? '');

$uploadDir = __DIR__ . '/uploads/';
$cacheDir  = __DIR__ . '/cache/';

/* 1️⃣ Strict filename validation */
if (
    $file === '' ||
    strpos($file, '..') !== false ||
    str_starts_with($file, '/')
) {
    http_response_code(400);
    exit('Invalid file parameter.');
}

/* 2️⃣ Ensure cache directory exists */
if (!is_dir($cacheDir)) {
    if (!mkdir($cacheDir, 0755, true)) {
        http_response_code(500);
        exit('Failed to create cache directory.');
    }
}

/* 3️⃣ Resolve real path safely */
$src = realpath($uploadDir . $file);
if (!$src || !str_starts_with($src, realpath($uploadDir))) {
    http_response_code(404);
    exit('File not found.');
}

/* 4️⃣ Extension whitelist */
$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
$allowed = ['docx','xlsx','pptx','odt','ods','odp','rtf','csv','pdf'];

if (!in_array($ext, $allowed, true)) {
    http_response_code(415);
    exit('Unsupported file type.');
}

/* 5️⃣ Hash-based cached PDF filename */
$hash = md5($src . filemtime($src));
$pdf  = $cacheDir . $hash . '.pdf';

/* 6️⃣ If file is cached, serve directly */
if (file_exists($pdf)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($file, '.' . $ext) . '.pdf"');
    readfile($pdf);
    exit;
}

/* 7️⃣ If PDF already uploaded, copy to cache */
if ($ext === 'pdf') {
    if (!copy($src, $pdf)) {
        http_response_code(500);
        exit('Failed to cache PDF.');
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($file) . '"');
    readfile($pdf);
    exit;
}

/* 8️⃣ For large Office files: show live progress */
if (ob_get_level()) ob_end_clean(); // clean buffer
header('Content-Type: text/html; charset=utf-8');
echo <<<HTML
<!DOCTYPE html>
<html>
<head>
<style>
body { font-family: sans-serif; text-align: center; margin-top: 50px; }
.loader {
  border: 8px solid #f3f3f3;
  border-top: 8px solid #333;
  border-radius: 50%;
  width: 60px;
  height: 60px;
  margin: 0 auto 20px;
  animation: spin 1s linear infinite;
}
@keyframes spin { 100% { transform: rotate(360deg); } }
#log { white-space: pre-wrap; text-align: left; max-width: 600px; margin: 20px auto; border:1px solid #ccc; padding:10px; }
</style>
    <link rel="stylesheet" href="dark-mode.css">
</head>
<body>
<h3>Generating preview, please wait...</h3>
<div class="loader"></div>
<div id="log"></div>
<script>
function log(msg) {
    const logDiv = document.getElementById('log');
    logDiv.textContent += msg + "\\n";
    window.scrollTo(0, document.body.scrollHeight);
}
</script>
HTML;

flush();

/* 9️⃣ Run LibreOffice with proc_open to stream output */
putenv('HOME=/tmp');

$descriptorspec = [
    1 => ['pipe', 'w'], // stdout
    2 => ['pipe', 'w']  // stderr
];

$cmd = sprintf(
    'libreoffice --headless --nologo --nofirststartwizard --convert-to pdf --outdir %s %s',
    escapeshellarg($cacheDir),
    escapeshellarg($src)
);

$process = proc_open($cmd, $descriptorspec, $pipes);

if (is_resource($process)) {
    while (($line = fgets($pipes[1])) !== false) {
        $line = htmlspecialchars($line, ENT_QUOTES);
        echo "<script>log('". $line ."');</script>";
        flush();
    }
    while (($line = fgets($pipes[2])) !== false) {
        $line = htmlspecialchars($line, ENT_QUOTES);
        echo "<script>log('ERR: ". $line ."');</script>";
        flush();
    }

    fclose($pipes[1]);
    fclose($pipes[2]);

    $ret = proc_close($process);
} else {
    echo "<script>log('Failed to start LibreOffice process');</script>";
    exit;
}

/* 10️⃣ Check generated PDF */
$generatedPdf = $cacheDir . pathinfo($src, PATHINFO_FILENAME) . '.pdf';
if (!file_exists($generatedPdf)) {
    echo "<script>log('Preview generation failed. Exit code: $ret');</script>";
    exit;
}

// Rename to hash cache
rename($generatedPdf, $pdf);

// Redirect to serve PDF
echo "<script>log('Preview ready! Redirecting...');</script>";
echo "<script>window.location.href='?file=" . urlencode($file) . "';</script>";
echo "</body></html>";
exit;
?>

