<?php
/**
 * Çok Basit Kullanıcı API - Minimal Test
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

// Get action
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=bonusyalitim_ts825;charset=utf8mb4",
                   "bonusyalitim_ts825", "ts825_2025", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    if ($action === 'list_users') {
        // Kullanıcı listesi - Gerçek sütun adları
        $stmt = $pdo->query("SELECT id, username, full_name, email, role, is_active as status, last_login, created_at, updated_at FROM users ORDER BY id");
        $users = $stmt->fetchAll();

        // Status'u frontend için uyumlu hale getir
        foreach ($users as &$user) {
            $user['status'] = $user['status'] ? 'active' : 'inactive';
            $user['phone'] = ''; // Veritabanında phone sütunu yok
            $user['company'] = ''; // Veritabanında company sütunu yok
            $user['title'] = ''; // Veritabanında title sütunu yok
        }

        echo json_encode([
            'success' => true,
            'data' => $users,
            'count' => count($users),
            'source' => 'users-basic',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'user_stats') {
        // Kullanıcı istatistikleri - Gerçek sütun adları
        $total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $active = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
        $admins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        $recent = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

        echo json_encode([
            'success' => true,
            'data' => [
                'total' => (int)$total,
                'active' => (int)$active,
                'admins' => (int)$admins,
                'recent' => (int)$recent
            ],
            'source' => 'users-basic',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Kullanıcı oluşturma
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['username']) || empty($input['email']) || empty($input['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Zorunlu alanlar eksik'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$input['username']]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Kullanıcı adı zaten kullanılıyor'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'E-posta adresi zaten kullanılıyor'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Hash password
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);

        // Insert user - Gerçek sütun adları
        $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password_hash, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");

        $stmt->execute([
            $input['username'],
            $input['full_name'] ?? '',
            $input['email'],
            $hashedPassword,
            $input['role'] ?? 'user'
        ]);

        $userId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Kullanıcı başarıyla oluşturuldu',
            'user_id' => $userId,
            'source' => 'users-basic',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // Test endpoint
        echo json_encode([
            'success' => true,
            'message' => 'Users Basic API çalışıyor',
            'available_actions' => ['list_users', 'user_stats', 'create_user'],
            'source' => 'users-basic',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı hatası: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'source' => 'users-basic'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Genel hata: ' . $e->getMessage(),
        'source' => 'users-basic'
    ], JSON_UNESCAPED_UNICODE);
}
?>
