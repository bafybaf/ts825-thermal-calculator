<?php
/**
 * BONUS TS 825 - Basit İstatistik API (Session Gerektirmez)
 */

// CORS headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Basit JSON response fonksiyonu
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Hata loglama
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - Stats Simple API: " . $message . PHP_EOL, 3, '../logs/php_errors.log');
}

try {
    // Config dosyasını yükle
    if (!file_exists('config.php')) {
        throw new Exception('Config dosyası bulunamadı');
    }
    require_once 'config.php';

    // Sabitleri kontrol et
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
        throw new Exception('Veritabanı sabitleri tanımlanmamış');
    }

    // Veritabanı bağlantısını test et
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    logError("Veritabanı bağlantısı başarılı");

    // İstatistikleri al
    $sql = "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(total_area) as total_area
            FROM projects";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats = $stmt->fetch();

    // Response
    sendResponse([
        'success' => true,
        'data' => [
            'projects' => [
                'total' => (int)$stats['total'],
                'draft' => (int)$stats['draft'],
                'in_progress' => (int)$stats['in_progress'],
                'completed' => (int)$stats['completed'],
                'total_area' => (float)$stats['total_area']
            ],
            'calculations' => 0
        ],
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD']
    ]);

} catch (PDOException $e) {
    logError("Veritabanı hatası: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'error' => 'Veritabanı bağlantı hatası: ' . $e->getMessage(),
        'code' => $e->getCode()
    ], 500);

} catch (Exception $e) {
    logError("Genel hata: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'error' => 'Sunucu hatası: ' . $e->getMessage()
    ], 500);
}
?>
