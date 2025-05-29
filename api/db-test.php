<?php
/**
 * Veritabanı Test API - Bağlantı ve Tablo Kontrolü
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Veritabanı bilgileri
$host = 'localhost';
$dbname = 'bonusyalitim_ts825';
$username = 'bonusyalitim_ts825';
$password = 'ts825_2025';

$response = [
    'success' => false,
    'message' => 'Veritabanı test başlatılıyor...',
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

try {
    // 1. PDO Bağlantı Testi
    $response['tests']['pdo_connection'] = [
        'name' => 'PDO Bağlantı Testi',
        'status' => 'testing'
    ];

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    $response['tests']['pdo_connection']['status'] = 'success';
    $response['tests']['pdo_connection']['message'] = 'Veritabanı bağlantısı başarılı';

    // 2. Users Tablosu Varlık Testi
    $response['tests']['users_table'] = [
        'name' => 'Users Tablosu Kontrolü',
        'status' => 'testing'
    ];

    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        $response['tests']['users_table']['status'] = 'success';
        $response['tests']['users_table']['message'] = 'Users tablosu mevcut';
    } else {
        $response['tests']['users_table']['status'] = 'error';
        $response['tests']['users_table']['message'] = 'Users tablosu bulunamadı';
    }

    // 3. Tablo Yapısı Kontrolü
    $response['tests']['table_structure'] = [
        'name' => 'Tablo Yapısı Kontrolü',
        'status' => 'testing'
    ];

    try {
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll();
        $response['tests']['table_structure']['status'] = 'success';
        $response['tests']['table_structure']['columns'] = array_column($columns, 'Field');
        $response['tests']['table_structure']['message'] = 'Tablo yapısı okundu: ' . count($columns) . ' sütun';
    } catch (Exception $e) {
        $response['tests']['table_structure']['status'] = 'error';
        $response['tests']['table_structure']['message'] = 'Tablo yapısı okunamadı: ' . $e->getMessage();
    }

    // 4. Kullanıcı Sayısı Testi
    $response['tests']['user_count'] = [
        'name' => 'Kullanıcı Sayısı Kontrolü',
        'status' => 'testing'
    ];

    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetchColumn();
        $response['tests']['user_count']['status'] = 'success';
        $response['tests']['user_count']['count'] = $count;
        $response['tests']['user_count']['message'] = "Toplam $count kullanıcı bulundu";
    } catch (Exception $e) {
        $response['tests']['user_count']['status'] = 'error';
        $response['tests']['user_count']['message'] = 'Kullanıcı sayısı alınamadı: ' . $e->getMessage();
    }

    // 5. Örnek Kullanıcı Verisi Testi
    $response['tests']['sample_users'] = [
        'name' => 'Örnek Kullanıcı Verisi',
        'status' => 'testing'
    ];

    try {
        $stmt = $pdo->query("SELECT id, username, full_name, email, role, status FROM users LIMIT 3");
        $users = $stmt->fetchAll();
        $response['tests']['sample_users']['status'] = 'success';
        $response['tests']['sample_users']['users'] = $users;
        $response['tests']['sample_users']['message'] = count($users) . ' kullanıcı örneği alındı';
    } catch (Exception $e) {
        $response['tests']['sample_users']['status'] = 'error';
        $response['tests']['sample_users']['message'] = 'Kullanıcı verileri alınamadı: ' . $e->getMessage();
    }

    // 6. İstatistik Sorgusu Testi
    $response['tests']['stats_query'] = [
        'name' => 'İstatistik Sorgusu Testi',
        'status' => 'testing'
    ];

    try {
        $stats = [];
        
        // Total users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $stats['total'] = $stmt->fetchColumn();
        
        // Active users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
        $stats['active'] = $stmt->fetchColumn();
        
        // Admin users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stats['admins'] = $stmt->fetchColumn();
        
        // Recent users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['recent'] = $stmt->fetchColumn();

        $response['tests']['stats_query']['status'] = 'success';
        $response['tests']['stats_query']['stats'] = $stats;
        $response['tests']['stats_query']['message'] = 'İstatistik sorguları başarılı';
    } catch (Exception $e) {
        $response['tests']['stats_query']['status'] = 'error';
        $response['tests']['stats_query']['message'] = 'İstatistik sorguları başarısız: ' . $e->getMessage();
    }

    // Genel başarı durumu
    $allSuccess = true;
    foreach ($response['tests'] as $test) {
        if ($test['status'] !== 'success') {
            $allSuccess = false;
            break;
        }
    }

    $response['success'] = $allSuccess;
    $response['message'] = $allSuccess ? 'Tüm testler başarılı' : 'Bazı testler başarısız';

} catch (PDOException $e) {
    $response['tests']['pdo_connection']['status'] = 'error';
    $response['tests']['pdo_connection']['message'] = 'Veritabanı bağlantı hatası: ' . $e->getMessage();
    $response['tests']['pdo_connection']['error_code'] = $e->getCode();
    $response['message'] = 'Veritabanı bağlantısı başarısız';
} catch (Exception $e) {
    $response['message'] = 'Genel hata: ' . $e->getMessage();
    $response['error'] = [
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ];
}

// Response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
