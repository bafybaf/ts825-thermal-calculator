<?php
/**
 * BONUS TS 825 - Kullanıcı API
 */

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

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

// Database config
$host = 'localhost';
$dbname = 'bonusyalitim_ts825';
$username = 'bonusyalitim_ts825';
$password = 'ts825_2025';

// Get parameters
$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Database connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    if ($action === 'list_users') {
        // Kullanıcı listesi
        $stmt = $pdo->query("SELECT id, username, full_name, email, role, status, phone, company, title, created_at, last_login FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();

        sendResponse([
            'success' => true,
            'data' => $users,
            'count' => count($users),
            'source' => 'users-api',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } elseif ($action === 'user_stats') {
        // Kullanıcı istatistikleri
        $stats = [];

        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $stats['total'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
        $stats['active'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stats['admins'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['recent'] = (int)$stmt->fetchColumn();

        sendResponse([
            'success' => true,
            'data' => $stats,
            'source' => 'users-api',
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

        // Kullanıcıyı ekle
        $sql = "INSERT INTO users (username, full_name, email, password, role, status, phone, company, title, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW(), NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input['username'],
            isset($input['full_name']) ? $input['full_name'] : '',
            $input['email'],
            $hashedPassword,
            isset($input['role']) ? $input['role'] : 'user',
            isset($input['phone']) ? $input['phone'] : '',
            isset($input['company']) ? $input['company'] : '',
            isset($input['title']) ? $input['title'] : ''
        ]);

        $userId = $pdo->lastInsertId();

        sendResponse([
            'success' => true,
            'message' => 'Kullanıcı başarıyla oluşturuldu',
            'user_id' => $userId,
            'source' => 'users-api',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } elseif ($action === 'update_user' && $method === 'POST') {
        // Kullanıcı güncelleme
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$userId || !$input) {
            sendResponse(['success' => false, 'error' => 'Geçersiz veri'], 400);
        }

        $updateFields = [];
        $params = [];

        if (isset($input['full_name'])) {
            $updateFields[] = 'full_name = ?';
            $params[] = $input['full_name'];
        }
        if (isset($input['email'])) {
            $updateFields[] = 'email = ?';
            $params[] = $input['email'];
        }
        if (isset($input['phone'])) {
            $updateFields[] = 'phone = ?';
            $params[] = $input['phone'];
        }
        if (isset($input['company'])) {
            $updateFields[] = 'company = ?';
            $params[] = $input['company'];
        }
        if (isset($input['title'])) {
            $updateFields[] = 'title = ?';
            $params[] = $input['title'];
        }
        if (isset($input['role'])) {
            $updateFields[] = 'role = ?';
            $params[] = $input['role'];
        }
        if (isset($input['status'])) {
            $updateFields[] = 'status = ?';
            $params[] = $input['status'];
        }

        if (empty($updateFields)) {
            sendResponse(['success' => false, 'error' => 'Güncellenecek alan yok'], 400);
        }

        $updateFields[] = 'updated_at = NOW()';
        $params[] = $userId;

        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        sendResponse([
            'success' => true,
            'message' => 'Kullanıcı başarıyla güncellendi',
            'source' => 'users-api',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } elseif ($action === 'delete_user' && $method === 'DELETE') {
        // Kullanıcı silme
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if (!$userId) {
            sendResponse(['success' => false, 'error' => 'Kullanıcı ID gerekli'], 400);
        }

        if ($userId === 1) {
            sendResponse(['success' => false, 'error' => 'Ana admin silinemez'], 400);
        }

        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $username = $stmt->fetchColumn();

        if (!$username) {
            sendResponse(['success' => false, 'error' => 'Kullanıcı bulunamadı'], 404);
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);

        sendResponse([
            'success' => true,
            'message' => 'Kullanıcı başarıyla silindi',
            'deleted_user' => $username,
            'source' => 'users-api',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } elseif ($action === 'toggle_user_status' && $method === 'POST') {
        // Durum değiştirme
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if (!$userId) {
            sendResponse(['success' => false, 'error' => 'Kullanıcı ID gerekli'], 400);
        }

        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentStatus = $stmt->fetchColumn();

        if (!$currentStatus) {
            sendResponse(['success' => false, 'error' => 'Kullanıcı bulunamadı'], 404);
        }

        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';

        $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);

        sendResponse([
            'success' => true,
            'message' => 'Kullanıcı durumu değiştirildi',
            'old_status' => $currentStatus,
            'new_status' => $newStatus,
            'source' => 'users-api',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } else {
        // Test endpoint
        sendResponse([
            'success' => true,
            'message' => 'Users API çalışıyor',
            'available_actions' => ['list_users', 'user_stats', 'create_user', 'update_user', 'delete_user', 'toggle_user_status'],
            'source' => 'users-api',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

} catch (PDOException $e) {
    sendResponse([
        'success' => false,
        'error' => 'Veritabanı hatası: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'source' => 'users-api',
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
        'source' => 'users-api',
        'debug' => [
            'action' => $action,
            'method' => $method,
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], 500);
}
?>
