<?php
// api.php - The "Brain"
$upload_dir = __DIR__ . "/uploads/";
$clip_file = __DIR__ . "/clipboard.txt";

if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

// 1. FETCH DATA (Recursive scan for all files)
if (isset($_GET['fetch'])) {
    $files = [];
    if (is_dir($upload_dir)) {
        $dir = new RecursiveDirectoryIterator($upload_dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dir);

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($upload_dir, '', $file->getPathname());
                $files[] = [
                    'name' => $file->getFilename(),
                    'path' => $relativePath,
                    'ext' => strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION))
                ];
            }
        }
    }
    
    // Sort files by name
    usort($files, function($a, $b) { return strcmp($a['name'], $b['name']); });

    header('Content-Type: application/json');
    echo json_encode([
        'clipboard' => file_exists($clip_file) ? file_get_contents($clip_file) : "",
        'files' => $files
    ]);
    exit;
}

// 2. SMART UPLOAD (Handles Folders & Renaming)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload'])) {
    $paths = $_POST['paths']; 

    foreach ($_FILES['fileToUpload']['name'] as $k => $v) {
        if ($_FILES['fileToUpload']['error'][$k] === UPLOAD_ERR_OK) {
            
            $relative_path = $paths[$k];
            $target_file = $upload_dir . $relative_path;
            
            // Smart Rename Logic if file exists
            if (file_exists($target_file)) {
                $info = pathinfo($target_file);
                $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
                $name_only = $info['dirname'] . '/' . $info['filename'];
                $counter = 1;
                while (file_exists($name_only . " ($counter)" . $ext)) {
                    $counter++;
                }
                $target_file = $name_only . " ($counter)" . $ext;
            }

            $target_folder = dirname($target_file);
            if (!is_dir($target_folder)) mkdir($target_folder, 0775, true);

            move_uploaded_file($_FILES['fileToUpload']['tmp_name'][$k], $target_file);
        }
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// 3. SAVE CLIPBOARD
if (isset($_POST['save_clip'])) {
    file_put_contents($clip_file, $_POST['clipboard_text']);
    exit;
}
?>
