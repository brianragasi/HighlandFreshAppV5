<?php
// Test nested folder PHP access
header('Content-Type: application/json');

$dir = __DIR__;
$files = scandir($dir);

echo json_encode([
    'status' => 'ok', 
    'folder' => 'qc', 
    'path' => __FILE__,
    'directory' => $dir,
    'files_in_qc_folder' => $files,
    'dashboard_exists' => file_exists($dir . '/dashboard.php'),
    'dashboard_readable' => is_readable($dir . '/dashboard.php')
]);
