<?php
/**
 * Veritabanı Kontrol API - Basit Test
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$response = [
    'success' => false,
    'message' => 'Veritabanı kontrol başlatılıyor...',
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

try {
    // Database config
    $host = 'localhost';
    $dbname = 'bonusyalitim_ts825';
    $username = 'bonusyalitim_ts825';
    $password = 'ts825_2025';

    $response['config'] = [
        'host' => $host,
        'database' => $dbname,
        'username' => $username
    ];

    // Test 1: PDO Connection
    $response['tests']['connection'] = ['status' => 'testing'];

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $response['tests']['connection'] = [
        'status' => 'success',
        'message' => 'Veritabanı bağlantısı başarılı'
    ];

    // Test 2: Users table exists
    $response['tests']['users_table'] = ['status' => 'testing'];

    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        $response['tests']['users_table'] = [
            'status' => 'success',
            'message' => 'Users tablosu mevcut'
        ];
    } else {
        $response['tests']['users_table'] = [
            'status' => 'error',
            'message' => 'Users tablosu bulunamadı'
        ];
    }

    // Test 3: Table structure
    $response['tests']['table_structure'] = ['status' => 'testing'];

    try {
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll();
        $response['tests']['table_structure'] = [
            'status' => 'success',
            'columns' => array_column($columns, 'Field'),
            'message' => count($columns) . ' sütun bulundu'
        ];
    } catch (Exception $e) {
        $response['tests']['table_structure'] = [
            'status' => 'error',
            'message' => 'Tablo yapısı okunamadı: ' . $e->getMessage()
        ];
    }

    // Test 4: Count users
    $response['tests']['user_count'] = ['status' => 'testing'];

    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetchColumn();
        $response['tests']['user_count'] = [
            'status' => 'success',
            'count' => $count,
            'message' => "$count kullanıcı bulundu"
        ];
    } catch (Exception $e) {
        $response['tests']['user_count'] = [
            'status' => 'error',
            'message' => 'Kullanıcı sayısı alınamadı: ' . $e->getMessage()
        ];
    }

    // Test 5: Sample users
    $response['tests']['sample_users'] = ['status' => 'testing'];

    try {
        $stmt = $pdo->query("SELECT id, username, full_name, email, role, is_active, created_at FROM users LIMIT 3");
        $users = $stmt->fetchAll();
        $response['tests']['sample_users'] = [
            'status' => 'success',
            'users' => $users,
            'message' => count($users) . ' örnek kullanıcı alındı'
        ];
    } catch (Exception $e) {
        $response['tests']['sample_users'] = [
            'status' => 'error',
            'message' => 'Kullanıcı verileri alınamadı: ' . $e->getMessage()
        ];
    }

    // Test 6: Simple query test
    $response['tests']['simple_query'] = ['status' => 'testing'];

    try {
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetchColumn();
        $response['tests']['simple_query'] = [
            'status' => 'success',
            'result' => $result,
            'message' => 'Basit sorgu başarılı'
        ];
    } catch (Exception $e) {
        $response['tests']['simple_query'] = [
            'status' => 'error',
            'message' => 'Basit sorgu başarısız: ' . $e->getMessage()
        ];
    }

    // Overall success
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
    $response['tests']['connection'] = [
        'status' => 'error',
        'message' => 'Veritabanı bağlantı hatası: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ];
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
