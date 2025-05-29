<?php
/**
 * BONUS TS 825 Hesap Programı
 * Test API - Kullanıcı Yönetimi için Basit API
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get action
$action = $_GET['action'] ?? 'test';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $response = [
        'success' => true,
        'message' => 'Test API çalışıyor',
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $method,
        'action' => $action,
        'source' => 'test-api'
    ];

    if ($action === 'list_users') {
        $response['data'] = [
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
        $response['count'] = count($response['data']);
        $response['message'] = 'Kullanıcı listesi (test verisi)';
    }

    elseif ($action === 'user_stats') {
        $response['data'] = [
            'total' => 3,
            'active' => 3,
            'admins' => 1,
            'recent' => 2
        ];
        $response['message'] = 'Kullanıcı istatistikleri (test verisi)';
    }

    elseif ($action === 'create_user' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            throw new Exception('Geçersiz JSON verisi');
        }

        $required = ['username', 'full_name', 'email', 'password', 'role'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new Exception("Zorunlu alan eksik: $field");
            }
        }

        $newUserId = rand(100, 999);

        $response['data'] = [
            'id' => $newUserId,
            'username' => $input['username'],
            'full_name' => $input['full_name'],
            'email' => $input['email'],
            'role' => $input['role'],
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        $response['user_id'] = $newUserId;
        $response['message'] = 'Kullanıcı başarıyla oluşturuldu (simülasyon)';
    }

    else {
        $response['data'] = [
            'php_version' => phpversion(),
            'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'available_actions' => ['list_users', 'user_stats', 'create_user']
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'source' => 'test-api',
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'action' => $action,
            'method' => $method
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
