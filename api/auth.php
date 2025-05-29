<?php
/**
 * BONUS TS 825 Hesap Programı
 * Kullanıcı Kimlik Doğrulama API
 */

require_once 'config.php';

class AuthManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function register($data) {
        // Validasyon
        $required = ['username', 'email', 'password', 'full_name'];
        $errors = validateRequired($required, $data);
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        if (!validateEmail($data['email'])) {
            return ['success' => false, 'error' => 'Geçersiz email adresi'];
        }

        if (strlen($data['password']) < 6) {
            return ['success' => false, 'error' => 'Şifre en az 6 karakter olmalıdır'];
        }

        // Kullanıcı adı ve email kontrolü
        if ($this->userExists($data['username'], $data['email'])) {
            return ['success' => false, 'error' => 'Bu kullanıcı adı veya email zaten kullanılıyor'];
        }

        try {
            $sql = "INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $stmt->execute([
                sanitize($data['username']),
                sanitize($data['email']),
                $passwordHash,
                sanitize($data['full_name'])
            ]);

            $userId = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Kayıt başarılı',
                'user_id' => $userId
            ];

        } catch (PDOException $e) {
            logError("Kayıt hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Kayıt işlemi başarısız'];
        }
    }

    public function login($data) {
        $required = ['username', 'password'];
        $errors = validateRequired($required, $data);
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            // Kullanıcıyı bul (username veya email ile)
            $sql = "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([sanitize($data['username']), sanitize($data['username'])]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($data['password'], $user['password_hash'])) {
                return ['success' => false, 'error' => 'Geçersiz kullanıcı adı veya şifre'];
            }

            // Session token oluştur
            $sessionToken = $this->createSession($user['id']);
            
            // Son giriş zamanını güncelle
            $this->updateLastLogin($user['id']);

            return [
                'success' => true,
                'message' => 'Giriş başarılı',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ],
                'session_token' => $sessionToken
            ];

        } catch (PDOException $e) {
            logError("Giriş hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Giriş işlemi başarısız'];
        }
    }

    public function logout($sessionToken) {
        try {
            $sql = "DELETE FROM user_sessions WHERE session_token = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$sessionToken]);
            
            return ['success' => true, 'message' => 'Çıkış başarılı'];
        } catch (PDOException $e) {
            logError("Çıkış hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Çıkış işlemi başarısız'];
        }
    }

    public function validateSession($sessionToken) {
        try {
            $sql = "SELECT u.*, s.expires_at 
                    FROM users u 
                    JOIN user_sessions s ON u.id = s.user_id 
                    WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$sessionToken]);
            $user = $stmt->fetch();

            if ($user) {
                // Session süresini uzat
                $this->extendSession($sessionToken);
                
                return [
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'full_name' => $user['full_name'],
                        'role' => $user['role']
                    ]
                ];
            }

            return ['success' => false, 'error' => 'Geçersiz oturum'];
        } catch (PDOException $e) {
            logError("Session doğrulama hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Session doğrulama başarısız'];
        }
    }

    public function changePassword($userId, $oldPassword, $newPassword) {
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'error' => 'Yeni şifre en az 6 karakter olmalıdır'];
        }

        try {
            // Mevcut şifreyi kontrol et
            $sql = "SELECT password_hash FROM users WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Mevcut şifre yanlış'];
            }

            // Yeni şifreyi güncelle
            $sql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt->execute([$newPasswordHash, $userId]);

            return ['success' => true, 'message' => 'Şifre başarıyla değiştirildi'];

        } catch (PDOException $e) {
            logError("Şifre değiştirme hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Şifre değiştirme başarısız'];
        }
    }

    private function userExists($username, $email) {
        $sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([sanitize($username), sanitize($email)]);
        return $stmt->fetch() !== false;
    }

    private function createSession($userId) {
        $sessionToken = generateToken(64);
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $sql = "INSERT INTO user_sessions (user_id, session_token, expires_at, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $sessionToken, $expiresAt, $ipAddress, $userAgent]);

        return $sessionToken;
    }

    private function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
    }

    private function extendSession($sessionToken) {
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        $sql = "UPDATE user_sessions SET expires_at = ? WHERE session_token = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$expiresAt, $sessionToken]);
    }

    public function cleanExpiredSessions() {
        $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }
}

// API endpoint handling
$authManager = new AuthManager();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            switch ($action) {
                case 'register':
                    $result = $authManager->register($input);
                    sendJsonResponse($result, $result['success'] ? 201 : 400);
                    break;
                    
                case 'login':
                    $result = $authManager->login($input);
                    sendJsonResponse($result, $result['success'] ? 200 : 401);
                    break;
                    
                case 'logout':
                    $sessionToken = $input['session_token'] ?? '';
                    $result = $authManager->logout($sessionToken);
                    sendJsonResponse($result);
                    break;
                    
                case 'change_password':
                    $sessionToken = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                    $sessionResult = $authManager->validateSession($sessionToken);
                    
                    if (!$sessionResult['success']) {
                        sendJsonResponse(['success' => false, 'error' => 'Oturum geçersiz'], 401);
                    }
                    
                    $result = $authManager->changePassword(
                        $sessionResult['user']['id'],
                        $input['old_password'],
                        $input['new_password']
                    );
                    sendJsonResponse($result);
                    break;
                    
                default:
                    sendJsonResponse(['success' => false, 'error' => 'Geçersiz işlem'], 400);
            }
            break;
            
        case 'GET':
            switch ($action) {
                case 'validate':
                    $sessionToken = $_SERVER['HTTP_AUTHORIZATION'] ?? $_GET['token'] ?? '';
                    $result = $authManager->validateSession($sessionToken);
                    sendJsonResponse($result, $result['success'] ? 200 : 401);
                    break;
                    
                case 'cleanup':
                    $authManager->cleanExpiredSessions();
                    sendJsonResponse(['success' => true, 'message' => 'Expired sessions cleaned']);
                    break;
                    
                default:
                    sendJsonResponse(['success' => false, 'error' => 'Geçersiz işlem'], 400);
            }
            break;
            
        default:
            sendJsonResponse(['success' => false, 'error' => 'Desteklenmeyen HTTP metodu'], 405);
    }

} catch (Exception $e) {
    logError("Auth API hatası: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'error' => 'Sunucu hatası'], 500);
}
?>
