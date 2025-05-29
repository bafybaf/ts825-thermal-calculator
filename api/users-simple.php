<?php
/**
 * BONUS TS 825 - Basit Kullanıcı API
 */

// Hata raporlamayı aç
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

// Simple response function
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Get parameters
$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Database config
    $host = 'localhost';
    $dbname = 'bonusyalitim_ts825';
    $username = 'bonusyalitim_ts825';
    $password = 'ts825_2025';

    // Database connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    if ($action === 'list_users') {
        // Kullanıcı listesi - Gerçek sütun adları
        $stmt = $pdo->query("SELECT id, username, full_name, email, role, is_active as status, last_login, created_at, updated_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();

        // Status'u frontend için uyumlu hale getir
        foreach ($users as &$user) {
            $user['status'] = $user['status'] ? 'active' : 'inactive';
            $user['phone'] = ''; // Veritabanında phone sütunu yok
            $user['company'] = ''; // Veritabanında company sütunu yok
            $user['title'] = ''; // Veritabanında title sütunu yok
        }

        sendResponse([
            'success' => true,
            'data' => $users,
            'count' => count($users),
            'source' => 'users-simple',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } elseif ($action === 'user_stats') {
        // Kullanıcı istatistikleri
        $stats = [];

        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $stats['total'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
        $stats['active'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stats['admins'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['recent'] = (int)$stmt->fetchColumn();

        sendResponse([
            'success' => true,
            'data' => $stats,
            'source' => 'users-simple',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } elseif ($action === 'create_user' && $method === 'POST') {
        // Kullanıcı oluşturma
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['username']) || empty($input['email']) || empty($input['password'])) {
            sendResponse(['success' => false, 'error' => 'Zorunlu alanlar eksik'], 400);
        }

        // Username kontrolü
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$input['username']]);
        if ($stmt->fetch()) {
            sendResponse(['success' => false, 'error' => 'Bu kullanıcı adı zaten kullanılıyor'], 400);
        }

        // Email kontrolü
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);
        if ($stmt->fetch()) {
            sendResponse(['success' => false, 'error' => 'Bu e-posta adresi zaten kullanılıyor'], 400);
        }

        // Şifreyi hash'le
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);

        // Kullanıcıyı ekle - Gerçek sütun adları
        $sql = "INSERT INTO users (username, full_name, email, password_hash, role, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input['username'],
            isset($input['full_name']) ? $input['full_name'] : '',
            $input['email'],
            $hashedPassword,
            isset($input['role']) ? $input['role'] : 'user'
        ]);

        $userId = $pdo->lastInsertId();

        sendResponse([
            'success' => true,
            'message' => 'Kullanıcı başarıyla oluşturuldu',
            'user_id' => $userId,
            'source' => 'users-simple',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } else {
        // Test endpoint
        sendResponse([
            'success' => true,
            'message' => 'Users Simple API çalışıyor',
            'available_actions' => ['list_users', 'user_stats', 'create_user'],
            'source' => 'users-simple',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

} catch (PDOException $e) {
    sendResponse([
        'success' => false,
        'error' => 'Veritabanı hatası: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'source' => 'users-simple',
        'debug' => [
            'action' => $action,
            'method' => $method,
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], 500);

} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'error' => 'Genel hata: ' . $e->getMessage(),
        'source' => 'users-simple',
        'debug' => [
            'action' => $action,
            'method' => $method,
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], 500);
}
?>
