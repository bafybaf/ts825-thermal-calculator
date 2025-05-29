<?php
/**
 * BONUS TS 825 - Basit Proje Listesi API (Session Gerektirmez)
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
    error_log(date('Y-m-d H:i:s') . " - List Simple API: " . $message . PHP_EOL, 3, '../logs/php_errors.log');
}

// Yardımcı fonksiyonlar
function getBuildingTypeName($type) {
    $types = [
        'residential' => 'Konut',
        'office' => 'Ofis',
        'commercial' => 'Ticari',
        'educational' => 'Eğitim',
        'healthcare' => 'Sağlık',
        'industrial' => 'Endüstriyel',
        'other' => 'Diğer'
    ];
    return $types[$type] ?? 'Bilinmiyor';
}

function getClimateZoneName($zone) {
    $zones = [
        1 => '1. Bölge (En Soğuk)',
        2 => '2. Bölge (Çok Soğuk)',
        3 => '3. Bölge (Soğuk)',
        4 => '4. Bölge (Ilık)',
        5 => '5. Bölge (Sıcak)',
        6 => '6. Bölge (En Sıcak)'
    ];
    return $zones[$zone] ?? 'Bilinmiyor';
}

function getStatusName($status) {
    $statuses = [
        'draft' => 'Taslak',
        'in_progress' => 'Devam Ediyor',
        'completed' => 'Tamamlandı'
    ];
    return $statuses[$status] ?? 'Bilinmiyor';
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

    // Projeleri al
    $limit = (int)($_GET['limit'] ?? 10);
    $sql = "SELECT * FROM projects ORDER BY created_at DESC LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    $projects = $stmt->fetchAll();

    // Formatla
    foreach ($projects as &$project) {
        $project['building_type_name'] = getBuildingTypeName($project['building_type']);
        $project['climate_zone_name'] = getClimateZoneName($project['climate_zone']);
        $project['status_name'] = getStatusName($project['status']);
        $project['created_at_formatted'] = date('d.m.Y H:i', strtotime($project['created_at']));
        $project['updated_at_formatted'] = date('d.m.Y H:i', strtotime($project['updated_at']));
    }

    // Response
    sendResponse([
        'success' => true,
        'data' => $projects,
        'count' => count($projects),
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
