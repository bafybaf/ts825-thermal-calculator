<?php
/**
 * Basit Veritabanı Test Dosyası
 * URL: https://ts825hesap.bonusyalitim.com.tr/db-test.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Güvenlik kontrolü
if (!isset($_GET['test']) || $_GET['test'] !== 'db') {
    die(json_encode(['error' => 'Test parametresi gerekli. URL: db-test.php?test=db']));
}

try {
    // Config dosyasını dahil et
    require_once 'api/config.php';
    
    echo json_encode([
        'step' => 'config_loaded',
        'success' => true,
        'message' => 'Config dosyası yüklendi',
        'constants' => [
            'DB_HOST' => DB_HOST,
            'DB_NAME' => DB_NAME,
            'DB_USER' => DB_USER,
            'DB_PASS' => substr(DB_PASS, 0, 3) . '***' // Güvenlik için kısalt
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'step' => 'config_error',
        'success' => false,
        'error' => 'Config yüklenemedi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo "\n" . json_encode(['step' => 'separator']) . "\n";

try {
    // Doğrudan PDO bağlantısı test et
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    echo json_encode([
        'step' => 'pdo_connection',
        'success' => true,
        'message' => 'PDO bağlantısı başarılı',
        'server_info' => $pdo->getAttribute(PDO::ATTR_SERVER_INFO),
        'server_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode([
        'step' => 'pdo_error',
        'success' => false,
        'error' => 'PDO bağlantı hatası: ' . $e->getMessage(),
        'code' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo "\n" . json_encode(['step' => 'separator']) . "\n";

try {
    // Database sınıfını test et
    $db = Database::getInstance()->getConnection();
    
    echo json_encode([
        'step' => 'database_class',
        'success' => true,
        'message' => 'Database sınıfı çalışıyor'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'step' => 'database_class_error',
        'success' => false,
        'error' => 'Database sınıfı hatası: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo "\n" . json_encode(['step' => 'separator']) . "\n";

try {
    // Tabloları kontrol et
    $tables = ['users', 'projects', 'building_elements', 'calculations'];
    $tableResults = [];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch();
            $tableResults[$table] = [
                'exists' => true,
                'count' => (int)$result['count']
            ];
        } catch (PDOException $e) {
            $tableResults[$table] = [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode([
        'step' => 'table_check',
        'success' => true,
        'message' => 'Tablo kontrolü tamamlandı',
        'tables' => $tableResults
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'step' => 'table_check_error',
        'success' => false,
        'error' => 'Tablo kontrolü hatası: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

echo "\n" . json_encode(['step' => 'separator']) . "\n";

try {
    // Basit sorgu test et
    $stmt = $pdo->query("SELECT 1 as test_value");
    $result = $stmt->fetch();
    
    echo json_encode([
        'step' => 'query_test',
        'success' => true,
        'message' => 'Basit sorgu başarılı',
        'result' => $result
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode([
        'step' => 'query_test_error',
        'success' => false,
        'error' => 'Sorgu test hatası: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

echo "\n" . json_encode([
    'step' => 'completed',
    'success' => true,
    'message' => 'Veritabanı test tamamlandı',
    'timestamp' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
?>
