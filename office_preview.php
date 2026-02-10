<?php
$file = $_GET['file'] ?? '';
$uploadDir = __DIR__ . '/uploads/';
$cacheDir  = __DIR__ . '/cache/';

@mkdir($cacheDir);

$src = realpath($uploadDir . $file);
if (!$src || !str_starts_with($src, realpath($uploadDir))) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
$allowed = ['docx','xlsx','pptx','odt','ods','odp','rtf','csv'];

if (!in_array($ext, $allowed)) {
    http_response_code(415);
    exit;
}

$hash = md5($src . filemtime($src));
$pdf  = $cacheDir . $hash . '.pdf';

if (!file_exists($pdf)) {
    $cmd = sprintf(
        'libreoffice --headless --nologo --nofirststartwizard --convert-to pdf --outdir %s %s',
        escapeshellarg($cacheDir),
        escapeshellarg($src)
    );
    exec($cmd);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline');
readfile($pdf);
