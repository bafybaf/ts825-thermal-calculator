<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database configuration
$host = 'localhost';
$dbname = 'bonusyalitim_ts825';
$username = 'bonusyalitim_ts825';
$password = 'ts825_2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı bağlantı hatası: ' . $e->getMessage()
    ]);
    exit;
}

// Get action from URL
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Simple authentication check
function requireAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Yetkilendirme gerekli']);
        exit;
    }
    
    // For demo purposes, accept any token
    return ['id' => 1, 'role' => 'admin'];
}

// Check admin permission
function requireAdmin($user) {
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Bu işlem için admin yetkisi gerekli']);
        exit;
    }
}

try {
    $user = requireAuth();
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'list_users':
                    requireAdmin($user);
                    
                    $stmt = $pdo->prepare("
                        SELECT id, username, full_name, email, role, status, 
                               phone, company, title, created_at, last_login
                        FROM users 
                        ORDER BY created_at DESC
                    ");
                    $stmt->execute();
                    $users = $stmt->fetchAll();
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $users
                    ]);
                    break;
                    
                case 'user_stats':
                    requireAdmin($user);
                    
                    $stats = [];
                    
                    // Total users
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users");
                    $stmt->execute();
                    $stats['total'] = $stmt->fetch()['total'];
                    
                    // Active users
                    $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM users WHERE status = 'active'");
                    $stmt->execute();
                    $stats['active'] = $stmt->fetch()['active'];
                    
                    // Admin users
                    $stmt = $pdo->prepare("SELECT COUNT(*) as admins FROM users WHERE role = 'admin'");
                    $stmt->execute();
                    $stats['admins'] = $stmt->fetch()['admins'];
                    
                    // Recent users (last 30 days)
                    $stmt = $pdo->prepare("SELECT COUNT(*) as recent FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                    $stmt->execute();
                    $stats['recent'] = $stmt->fetch()['recent'];
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $stats
                    ]);
                    break;
                    
                case 'user_detail':
                    $userId = $_GET['id'] ?? 0;
                    
                    if (!$userId) {
                        echo json_encode(['success' => false, 'error' => 'Kullanıcı ID gerekli']);
                        break;
                    }
                    
                    $stmt = $pdo->prepare("
                        SELECT id, username, full_name, email, role, status, 
                               phone, company, title, created_at, last_login
                        FROM users 
                        WHERE id = ?
                    ");
                    $stmt->execute([$userId]);
                    $user_data = $stmt->fetch();
                    
                    if (!$user_data) {
                        echo json_encode(['success' => false, 'error' => 'Kullanıcı bulunamadı']);
                        break;
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $user_data
                    ]);
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'error' => 'Geçersiz GET işlemi']);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            switch ($action) {
                case 'create_user':
                    requireAdmin($user);
                    
                    if (!$input) {
                        echo json_encode(['success' => false, 'error' => 'Geçersiz JSON verisi']);
                        break;
                    }
                    
                    $required = ['username', 'full_name', 'email', 'password', 'role'];
                    foreach ($required as $field) {
                        if (empty($input[$field])) {
                            echo json_encode(['success' => false, 'error' => "Zorunlu alan eksik: $field"]);
                            exit;
                        }
                    }
                    
                    // Check if username or email already exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$input['username'], $input['email']]);
                    if ($stmt->fetch()) {
                        echo json_encode(['success' => false, 'error' => 'Kullanıcı adı veya e-posta zaten kullanımda']);
                        break;
                    }
                    
                    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, full_name, email, password, role, status, phone, company, title, created_at)
                        VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $input['username'],
                        $input['full_name'],
                        $input['email'],
                        $hashedPassword,
                        $input['role'],
                        $input['phone'] ?? '',
                        $input['company'] ?? '',
                        $input['title'] ?? ''
                    ]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Kullanıcı başarıyla oluşturuldu',
                        'user_id' => $pdo->lastInsertId()
                    ]);
                    break;
                    
                case 'update_profile':
                    $userId = $user['id'];
                    
                    if (!$input) {
                        echo json_encode(['success' => false, 'error' => 'Geçersiz JSON verisi']);
                        break;
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
                    
                    // Password change
                    if (isset($input['new_password']) && isset($input['current_password'])) {
                        // Verify current password
                        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $currentHash = $stmt->fetchColumn();
                        
                        if (!password_verify($input['current_password'], $currentHash)) {
                            echo json_encode(['success' => false, 'error' => 'Mevcut şifre yanlış']);
                            break;
                        }
                        
                        $updateFields[] = 'password = ?';
                        $params[] = password_hash($input['new_password'], PASSWORD_DEFAULT);
                    }
                    
                    if (empty($updateFields)) {
                        echo json_encode(['success' => false, 'error' => 'Güncellenecek alan bulunamadı']);
                        break;
                    }
                    
                    $updateFields[] = 'updated_at = NOW()';
                    $params[] = $userId;
                    
                    $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Profil başarıyla güncellendi'
                    ]);
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'error' => 'Geçersiz POST işlemi']);
            }
            break;
            
        case 'PUT':
            // User update operations
            echo json_encode(['success' => false, 'error' => 'PUT işlemleri henüz desteklenmiyor']);
            break;
            
        case 'DELETE':
            // User delete operations
            echo json_encode(['success' => false, 'error' => 'DELETE işlemleri henüz desteklenmiyor']);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Desteklenmeyen HTTP metodu']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Sunucu hatası: ' . $e->getMessage()
    ]);
}
?>
