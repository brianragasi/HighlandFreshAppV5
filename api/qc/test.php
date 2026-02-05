<?php
// Test nested folder PHP access
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'folder' => 'qc', 'path' => __FILE__]);
