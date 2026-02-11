<?php
// Simple test for api/qc directory
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'QC directory is accessible',
    'file' => __FILE__,
    'dir' => __DIR__,
    'files_in_dir' => scandir(__DIR__)
]);
