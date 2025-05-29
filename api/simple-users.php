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

// Get action from URL
$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

// Simple response function
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Sample users data
function getSampleUsers() {
    return [
        [
            'id' => 1,
            'username' => 'admin',
            'full_name' => 'Sistem Yöneticisi',
            'email' => 'admin@bonusyalitim.com.tr',
            'role' => 'admin',
            'status' => 'active',
            'created_at' => '2024-01-01',
            'last_login' => '2024-12-20 10:30:00',
            'company' => 'Bonus Yalıtım',
            'title' => 'Sistem Yöneticisi',
            'phone' => '+90 555 123 4567'
        ],
        [
            'id' => 2,
            'username' => 'engineer1',
            'full_name' => 'Ahmet Yılmaz',
            'email' => 'ahmet@bonusyalitim.com.tr',
            'role' => 'user',
            'status' => 'active',
            'created_at' => '2024-05-15',
            'last_login' => '2024-12-19 16:45:00',
            'company' => 'Bonus Yalıtım',
            'title' => 'Makine Mühendisi',
            'phone' => '+90 555 234 5678'
        ],
        [
            'id' => 3,
            'username' => 'engineer2',
            'full_name' => 'Fatma Demir',
            'email' => 'fatma@bonusyalitim.com.tr',
            'role' => 'user',
            'status' => 'active',
            'created_at' => '2024-06-20',
            'last_login' => '2024-12-18 14:20:00',
            'company' => 'Bonus Yalıtım',
            'title' => 'İnşaat Mühendisi',
            'phone' => '+90 555 345 6789'
        ]
    ];
}

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'list_users':
                    $users = getSampleUsers();
                    sendResponse([
                        'success' => true,
                        'data' => $users,
                        'count' => count($users),
                        'source' => 'simple-users',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    break;

                case 'user_stats':
                    $users = getSampleUsers();
                    $stats = [
                        'total' => count($users),
                        'active' => count(array_filter($users, function($u) { return $u['status'] === 'active'; })),
                        'admins' => count(array_filter($users, function($u) { return $u['role'] === 'admin'; })),
                        'recent' => 2 // Son 30 gün
                    ];

                    sendResponse([
                        'success' => true,
                        'data' => $stats,
                        'source' => 'simple-users',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    break;

                case 'user_detail':
                    $userId = isset($_GET['id']) ? $_GET['id'] : 0;
                    $users = getSampleUsers();
                    $user = array_filter($users, function($u) use ($userId) { return $u['id'] == $userId; });

                    if (empty($user)) {
                        sendResponse(['success' => false, 'error' => 'Kullanıcı bulunamadı'], 404);
                    }

                    sendResponse([
                        'success' => true,
                        'data' => array_values($user)[0],
                        'source' => 'simple-users',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    break;

                default:
                    sendResponse([
                        'success' => true,
                        'message' => 'Simple Users API çalışıyor',
                        'available_actions' => ['list_users', 'user_stats', 'user_detail', 'create_user', 'update_profile'],
                        'source' => 'simple-users',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);

            switch ($action) {
                case 'create_user':
                    if (!$input) {
                        sendResponse(['success' => false, 'error' => 'Geçersiz JSON verisi'], 400);
                    }

                    $required = ['username', 'full_name', 'email', 'password', 'role'];
                    foreach ($required as $field) {
                        if (empty($input[$field])) {
                            sendResponse(['success' => false, 'error' => "Zorunlu alan eksik: $field"], 400);
                        }
                    }

                    // Simulate user creation
                    $newUserId = rand(100, 999);

                    sendResponse([
                        'success' => true,
                        'message' => 'Kullanıcı başarıyla oluşturuldu (simülasyon)',
                        'user_id' => $newUserId,
                        'data' => [
                            'id' => $newUserId,
                            'username' => $input['username'],
                            'full_name' => $input['full_name'],
                            'email' => $input['email'],
                            'role' => $input['role'],
                            'status' => 'active',
                            'created_at' => date('Y-m-d H:i:s')
                        ],
                        'source' => 'simple-users',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    break;

                case 'update_profile':
                    if (!$input) {
                        sendResponse(['success' => false, 'error' => 'Geçersiz JSON verisi'], 400);
                    }

                    // Simulate profile update
                    sendResponse([
                        'success' => true,
                        'message' => 'Profil başarıyla güncellendi (simülasyon)',
                        'updated_fields' => array_keys($input),
                        'source' => 'simple-users',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    break;

                default:
                    sendResponse(['success' => false, 'error' => 'Geçersiz POST işlemi'], 400);
            }
            break;

        default:
            sendResponse(['success' => false, 'error' => 'Desteklenmeyen HTTP metodu'], 405);
    }

} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'error' => 'Sunucu hatası: ' . $e->getMessage(),
        'source' => 'simple-users',
        'debug' => [
            'action' => $action,
            'method' => $method,
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], 500);
}
?>
