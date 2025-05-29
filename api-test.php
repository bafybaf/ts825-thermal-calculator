<?php
/**
 * Basit API Test Dosyası
 * URL: https://ts825.bonusyalitim.com.tr/api-test.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

echo json_encode([
    'success' => true,
    'message' => 'API Test Başarılı',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI']
], JSON_UNESCAPED_UNICODE);
?>
