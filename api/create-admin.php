<?php
/**
 * BONUS TS 825 Hesap Programı
 * Admin Kullanıcı Oluşturma
 */

require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Admin kullanıcısı var mı kontrol et
    $sql = "SELECT id FROM users WHERE username = 'admin' OR role = 'admin'";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $existingAdmin = $stmt->fetch();
    
    if ($existingAdmin) {
        echo json_encode([
            'success' => false,
            'message' => 'Admin kullanıcısı zaten mevcut'
        ]);
        exit;
    }
    
    // Admin kullanıcısı oluştur
    $adminData = [
        'username' => 'admin',
        'email' => 'admin@ts825.com',
        'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        'full_name' => 'Sistem Yöneticisi',
        'role' => 'admin'
    ];
    
    $sql = "INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $adminData['username'],
        $adminData['email'],
        $adminData['password_hash'],
        $adminData['full_name'],
        $adminData['role']
    ]);
    
    $adminId = $db->lastInsertId();
    
    // Demo normal kullanıcı da oluştur
    $userData = [
        'username' => 'demo',
        'email' => 'demo@ts825.com',
        'password_hash' => password_hash('demo123', PASSWORD_DEFAULT),
        'full_name' => 'Demo Kullanıcı',
        'role' => 'user'
    ];
    
    $stmt->execute([
        $userData['username'],
        $userData['email'],
        $userData['password_hash'],
        $userData['full_name'],
        $userData['role']
    ]);
    
    $userId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Admin ve demo kullanıcıları oluşturuldu',
        'admin_credentials' => [
            'username' => 'admin',
            'password' => 'admin123'
        ],
        'demo_credentials' => [
            'username' => 'demo',
            'password' => 'demo123'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Kullanıcı oluşturma hatası: ' . $e->getMessage()
    ]);
}
?>
