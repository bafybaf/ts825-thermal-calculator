<?php
/**
 * BONUS TS 825 - Düzeltilmiş Basit Veritabanı API
 */

// Hata raporlamayı aç (debug için)
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

// Basit JSON response
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Config dosyasını yükle
    if (!file_exists('config.php')) {
        sendResponse(['success' => false, 'error' => 'Config dosyası bulunamadı'], 500);
    }

    require_once 'config.php';

    // Veritabanı sabitleri kontrol
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
        sendResponse(['success' => false, 'error' => 'Veritabanı sabitleri eksik'], 500);
    }

    // Veritabanı bağlantısı
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ));

    $method = $_SERVER['REQUEST_METHOD'];
    $action = isset($_GET['action']) ? $_GET['action'] : 'test';

    if ($action === 'stats') {
        // İstatistikler
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    COALESCE(SUM(total_area), 0) as total_area
                FROM projects";

        $stmt = $pdo->query($sql);
        $stats = $stmt->fetch();

        sendResponse(array(
            'success' => true,
            'data' => array(
                'projects' => array(
                    'total' => (int)$stats['total'],
                    'draft' => (int)$stats['draft'],
                    'in_progress' => (int)$stats['in_progress'],
                    'completed' => (int)$stats['completed'],
                    'total_area' => (float)$stats['total_area']
                ),
                'calculations' => 0
            ),
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } elseif ($action === 'list') {
        // Proje listesi
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

        // LIMIT için güvenli değer kontrolü
        if ($limit < 1) $limit = 10;
        if ($limit > 100) $limit = 100;

        $sql = "SELECT * FROM projects ORDER BY created_at DESC LIMIT " . $limit;

        $stmt = $pdo->query($sql);
        $projects = $stmt->fetchAll();

        // Building type names
        $buildingTypes = array(
            'residential' => 'Konut',
            'office' => 'Ofis',
            'commercial' => 'Ticari',
            'educational' => 'Eğitim',
            'healthcare' => 'Sağlık',
            'industrial' => 'Endüstriyel',
            'other' => 'Diğer'
        );

        // Climate zone names
        $climateZones = array(
            1 => '1. Bölge (En Soğuk)',
            2 => '2. Bölge (Çok Soğuk)',
            3 => '3. Bölge (Soğuk)',
            4 => '4. Bölge (Ilık)',
            5 => '5. Bölge (Sıcak)',
            6 => '6. Bölge (En Sıcak)'
        );

        // Status names
        $statusNames = array(
            'draft' => 'Taslak',
            'in_progress' => 'Devam Ediyor',
            'completed' => 'Tamamlandı'
        );

        // Formatla
        foreach ($projects as $key => $project) {
            $projects[$key]['building_type_name'] = isset($buildingTypes[$project['building_type']]) ? $buildingTypes[$project['building_type']] : 'Bilinmiyor';
            $projects[$key]['climate_zone_name'] = isset($climateZones[$project['climate_zone']]) ? $climateZones[$project['climate_zone']] : 'Bilinmiyor';
            $projects[$key]['status_name'] = isset($statusNames[$project['status']]) ? $statusNames[$project['status']] : 'Bilinmiyor';
            $projects[$key]['created_at_formatted'] = date('d.m.Y H:i', strtotime($project['created_at']));
            $projects[$key]['updated_at_formatted'] = date('d.m.Y H:i', strtotime($project['updated_at']));
        }

        sendResponse(array(
            'success' => true,
            'data' => $projects,
            'count' => count($projects),
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } elseif ($action === 'create' && $method === 'POST') {
        // Proje oluşturma
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['name'])) {
            sendResponse(array('success' => false, 'error' => 'Proje adı gerekli'), 400);
        }

        // Building type prefix
        $prefixes = array(
            'residential' => 'KNT',
            'office' => 'OFS',
            'commercial' => 'TCR',
            'educational' => 'EGT',
            'healthcare' => 'SGL',
            'industrial' => 'END',
            'other' => 'DGR'
        );

        $buildingType = isset($input['building_type']) ? $input['building_type'] : 'other';
        $prefix = isset($prefixes[$buildingType]) ? $prefixes[$buildingType] : 'TST';
        $projectCode = $prefix . '-' . date('Y') . '-' . str_pad(time() % 1000000, 6, '0', STR_PAD_LEFT);

        $sql = "INSERT INTO projects (name, description, building_type, climate_zone, total_area, project_code, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', NOW(), NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            $input['name'],
            isset($input['description']) ? $input['description'] : '',
            $buildingType,
            isset($input['climate_zone']) ? (int)$input['climate_zone'] : 3,
            isset($input['total_area']) ? (float)$input['total_area'] : 0,
            $projectCode
        ));

        $projectId = $pdo->lastInsertId();

        sendResponse(array(
            'success' => true,
            'message' => 'Proje başarıyla oluşturuldu',
            'project_id' => $projectId,
            'project_code' => $projectCode,
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } elseif ($action === 'detail' && $method === 'GET') {
        // Proje detayı
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if (!$id) {
            sendResponse(array('success' => false, 'error' => 'Proje ID gerekli'), 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute(array($id));
        $project = $stmt->fetch();

        if (!$project) {
            sendResponse(array('success' => false, 'error' => 'Proje bulunamadı'), 404);
        }

        sendResponse(array(
            'success' => true,
            'data' => $project,
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } elseif ($action === 'fix-data' && $method === 'GET') {
        // Veri düzeltme işlemi
        try {
            // Yanlış yazılmış building_type'ları düzelt
            $sql = "UPDATE projects SET building_type = 'educational' WHERE building_type IN ('educatonal', 'educationl', 'education')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $fixedCount = $stmt->rowCount();

            sendResponse(array(
                'success' => true,
                'message' => "Veri düzeltme tamamlandı. {$fixedCount} kayıt düzeltildi.",
                'fixed_count' => $fixedCount,
                'source' => 'database-fixed',
                'timestamp' => date('Y-m-d H:i:s')
            ));
        } catch (PDOException $e) {
            sendResponse(array('success' => false, 'error' => 'Veri düzeltme hatası: ' . $e->getMessage()), 500);
        }

    } elseif ($action === 'update' && $method === 'POST') {
        // Proje güncelleme
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['id']) || empty($input['name'])) {
            sendResponse(array('success' => false, 'error' => 'Proje ID ve adı gerekli'), 400);
        }

        $sql = "UPDATE projects SET
                    name = ?,
                    description = ?,
                    building_type = ?,
                    climate_zone = ?,
                    total_area = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            $input['name'],
            isset($input['description']) ? $input['description'] : '',
            isset($input['building_type']) ? $input['building_type'] : 'other',
            isset($input['climate_zone']) ? (int)$input['climate_zone'] : 3,
            isset($input['total_area']) ? (float)$input['total_area'] : 0,
            isset($input['status']) ? $input['status'] : 'draft',
            (int)$input['id']
        ));

        if ($stmt->rowCount() > 0) {
            sendResponse(array(
                'success' => true,
                'message' => 'Proje başarıyla güncellendi',
                'project_id' => (int)$input['id'],
                'source' => 'database-fixed',
                'timestamp' => date('Y-m-d H:i:s')
            ));
        } else {
            sendResponse(array(
                'success' => false,
                'error' => 'Proje güncellenemedi veya değişiklik yapılmadı'
            ), 400);
        }

    } elseif ($action === 'list_users' && $method === 'GET') {
        // Kullanıcı listesi
        $sql = "SELECT id, username, full_name, email, role, status, phone, company, title, created_at, last_login FROM users ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll();

        sendResponse(array(
            'success' => true,
            'data' => $users,
            'count' => count($users),
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } elseif ($action === 'user_stats' && $method === 'GET') {
        // Kullanıcı istatistikleri
        $stats = array();

        // Total users
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $stats['total'] = $stmt->fetchColumn();

        // Active users
        $stmt = $pdo->query("SELECT COUNT(*) as active FROM users WHERE status = 'active'");
        $stats['active'] = $stmt->fetchColumn();

        // Admin users
        $stmt = $pdo->query("SELECT COUNT(*) as admins FROM users WHERE role = 'admin'");
        $stats['admins'] = $stmt->fetchColumn();

        // Recent users (last 30 days)
        $stmt = $pdo->query("SELECT COUNT(*) as recent FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stats['recent'] = $stmt->fetchColumn();

        sendResponse(array(
            'success' => true,
            'data' => $stats,
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } elseif ($action === 'detail' && $method === 'GET') {
        // Kullanıcı detayı
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if (!$userId) {
            sendResponse(array('success' => false, 'error' => 'Kullanıcı ID gerekli'), 400);
        }

        $stmt = $pdo->prepare("SELECT id, username, full_name, email, role, status, phone, company, title, created_at, updated_at, last_login FROM users WHERE id = ?");
        $stmt->execute(array($userId));
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse(array('success' => false, 'error' => 'Kullanıcı bulunamadı'), 404);
        }

        sendResponse(array(
            'success' => true,
            'data' => $user,
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } elseif ($action === 'create_user' && $method === 'POST') {
        // Kullanıcı oluşturma
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['username']) || empty($input['email']) || empty($input['password'])) {
            sendResponse(array('success' => false, 'error' => 'Kullanıcı adı, e-posta ve şifre gerekli'), 400);
        }

        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute(array($input['username'], $input['email']));
        if ($stmt->fetch()) {
            sendResponse(array('success' => false, 'error' => 'Kullanıcı adı veya e-posta zaten kullanımda'), 400);
        }

        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, full_name, email, password, role, status, phone, company, title, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW(), NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            $input['username'],
            isset($input['full_name']) ? $input['full_name'] : '',
            $input['email'],
            $hashedPassword,
            isset($input['role']) ? $input['role'] : 'user',
            isset($input['phone']) ? $input['phone'] : '',
            isset($input['company']) ? $input['company'] : '',
            isset($input['title']) ? $input['title'] : ''
        ));

        $userId = $pdo->lastInsertId();

        sendResponse(array(
            'success' => true,
            'message' => 'Kullanıcı başarıyla oluşturuldu',
            'user_id' => $userId,
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } elseif ($action === 'update_profile' && $method === 'POST') {
        // Profil güncelleme
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            sendResponse(array('success' => false, 'error' => 'Geçersiz JSON verisi'), 400);
        }

        // Basit kullanıcı ID (gerçek uygulamada session'dan alınır)
        $userId = 1;

        $updateFields = array();
        $params = array();

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
            // Verify current password (basit kontrol)
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute(array($userId));
            $currentHash = $stmt->fetchColumn();

            if ($currentHash && password_verify($input['current_password'], $currentHash)) {
                $updateFields[] = 'password = ?';
                $params[] = password_hash($input['new_password'], PASSWORD_DEFAULT);
            } else {
                sendResponse(array('success' => false, 'error' => 'Mevcut şifre yanlış'), 400);
            }
        }

        if (empty($updateFields)) {
            sendResponse(array('success' => false, 'error' => 'Güncellenecek alan bulunamadı'), 400);
        }

        $updateFields[] = 'updated_at = NOW()';
        $params[] = $userId;

        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        sendResponse(array(
            'success' => true,
            'message' => 'Profil başarıyla güncellendi',
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } elseif ($action === 'update_user' && $method === 'POST') {
        // Kullanıcı güncelleme (Admin)
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if (!$input || !$userId) {
            sendResponse(array('success' => false, 'error' => 'Geçersiz veri veya kullanıcı ID'), 400);
        }

        $updateFields = array();
        $params = array();

        if (isset($input['full_name'])) {
            $updateFields[] = 'full_name = ?';
            $params[] = $input['full_name'];
        }

        if (isset($input['email'])) {
            // Email benzersizlik kontrolü
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute(array($input['email'], $userId));
            if ($stmt->fetch()) {
                sendResponse(array('success' => false, 'error' => 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor'), 400);
            }
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
            sendResponse(array('success' => false, 'error' => 'Güncellenecek alan bulunamadı'), 400);
        }

        $updateFields[] = 'updated_at = NOW()';
        $params[] = $userId;

        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        sendResponse(array(
            'success' => true,
            'message' => 'Kullanıcı başarıyla güncellendi',
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } elseif ($action === 'delete_user' && $method === 'DELETE') {
        // Kullanıcı silme
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if (!$userId) {
            sendResponse(array('success' => false, 'error' => 'Kullanıcı ID gerekli'), 400);
        }

        // Admin kullanıcısını silmeyi engelle
        if ($userId === 1) {
            sendResponse(array('success' => false, 'error' => 'Ana admin kullanıcısı silinemez'), 400);
        }

        // Kullanıcının var olup olmadığını kontrol et
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute(array($userId));
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse(array('success' => false, 'error' => 'Kullanıcı bulunamadı'), 404);
        }

        // Kullanıcıyı sil
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute(array($userId));

        sendResponse(array(
            'success' => true,
            'message' => 'Kullanıcı başarıyla silindi',
            'deleted_user' => $user['username'],
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } elseif ($action === 'toggle_user_status' && $method === 'POST') {
        // Kullanıcı durumu değiştirme
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if (!$userId) {
            sendResponse(array('success' => false, 'error' => 'Kullanıcı ID gerekli'), 400);
        }

        // Mevcut durumu al
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $stmt->execute(array($userId));
        $currentStatus = $stmt->fetchColumn();

        if (!$currentStatus) {
            sendResponse(array('success' => false, 'error' => 'Kullanıcı bulunamadı'), 404);
        }

        // Durumu değiştir
        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';

        $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute(array($newStatus, $userId));

        sendResponse(array(
            'success' => true,
            'message' => 'Kullanıcı durumu başarıyla değiştirildi',
            'old_status' => $currentStatus,
            'new_status' => $newStatus,
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));

    } else {
        // Test endpoint
        sendResponse(array(
            'success' => true,
            'message' => 'DB Fixed API çalışıyor',
            'method' => $method,
            'action' => $action,
            'available_actions' => array('stats', 'list', 'create', 'detail', 'list_users', 'user_stats', 'create_user', 'update_profile'),
            'database' => array(
                'host' => DB_HOST,
                'name' => DB_NAME,
                'user' => DB_USER
            ),
            'source' => 'database-fixed',
            'timestamp' => date('Y-m-d H:i:s')
        ));
    }

} catch (PDOException $e) {
    sendResponse(array(
        'success' => false,
        'error' => 'Veritabanı hatası: ' . $e->getMessage(),
        'code' => $e->getCode(),
        'source' => 'database-fixed',
        'debug' => array(
            'action' => $action,
            'method' => $method,
            'input' => isset($input) ? $input : null,
            'sql_error' => $e->getMessage(),
            'sql_code' => $e->getCode(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        )
    ), 500);

} catch (Exception $e) {
    sendResponse(array(
        'success' => false,
        'error' => 'Genel hata: ' . $e->getMessage(),
        'source' => 'database-fixed',
        'debug' => array(
            'action' => $action,
            'method' => $method,
            'input' => isset($input) ? $input : null,
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        )
    ), 500);
}
?>
