<?php
/**
 * BONUS TS 825 Hesap Programı
 * Veritabanı ve Genel Konfigürasyon
 */

// Hata raporlama (Production için güvenli)
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0); // Production'da kapalı
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');

// Veritabanı konfigürasyonu
define('DB_HOST', 'localhost');
define('DB_NAME', 'bonusyalitim_ts825');
define('DB_USER', 'bonusyalitim_ts825');
define('DB_PASS', 'ts825_2025');
define('DB_CHARSET', 'utf8mb4');

// Uygulama konfigürasyonu
define('APP_NAME', 'BONUS TS 825 Hesap Programı');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost');

// Güvenlik
define('SECRET_KEY', 'BonusYalitim_TS825_SecureKey_2025_#$%^&*');
define('SESSION_TIMEOUT', 7200); // 2 saat (production için daha uzun)

// Dosya yükleme
define('UPLOAD_PATH', '../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// TS 825 Standart Değerleri
define('TS825_VERSION', '2024');

// İklim bölgeleri
$CLIMATE_ZONES = [
    1 => ['name' => '1. Bölge (En Soğuk)', 'hdd' => 4000],
    2 => ['name' => '2. Bölge (Çok Soğuk)', 'hdd' => 3000],
    3 => ['name' => '3. Bölge (Soğuk)', 'hdd' => 2000],
    4 => ['name' => '4. Bölge (Ilık)', 'hdd' => 1500],
    5 => ['name' => '5. Bölge (Sıcak)', 'hdd' => 1000],
    6 => ['name' => '6. Bölge (En Sıcak)', 'hdd' => 500]
];

// Yapı türleri
$BUILDING_TYPES = [
    'residential' => 'Konut',
    'office' => 'Ofis',
    'commercial' => 'Ticari',
    'educational' => 'Eğitim',
    'healthcare' => 'Sağlık',
    'industrial' => 'Endüstriyel',
    'other' => 'Diğer'
];

// Malzeme türleri
$MATERIAL_TYPES = [
    'concrete' => 'Beton',
    'brick' => 'Tuğla',
    'wood' => 'Ahşap',
    'steel' => 'Çelik',
    'insulation' => 'Yalıtım',
    'glass' => 'Cam',
    'other' => 'Diğer'
];

// Veritabanı bağlantısı
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Karakter seti ve collation'ı zorla ayarla
            $this->connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->connection->exec("SET CHARACTER SET utf8mb4");
            $this->connection->exec("SET character_set_connection=utf8mb4");
        } catch (PDOException $e) {
            die("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}

// Yardımcı fonksiyonlar
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function logError($message, $file = 'error.log') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($file, $logMessage, FILE_APPEND | LOCK_EX);
}

function sendJsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function validateRequired($fields, $data) {
    $errors = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[] = "$field alanı gereklidir";
        }
    }
    return $errors;
}

// Session başlatma
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CORS ayarları
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Veritabanı tablolarını oluştur
function createTables() {
    $db = Database::getInstance()->getConnection();

    // Kullanıcılar tablosu
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        role ENUM('user', 'admin') DEFAULT 'user',
        is_active BOOLEAN DEFAULT TRUE,
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->exec($sql);

    // Kullanıcı oturumları tablosu
    $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_token VARCHAR(255) UNIQUE NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $db->exec($sql);

    // Projeler tablosu (user_id eklendi)
    $sql = "CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        building_type VARCHAR(50) NOT NULL,
        climate_zone INT NOT NULL,
        total_area DECIMAL(10,2),
        status ENUM('draft', 'in_progress', 'completed') DEFAULT 'draft',
        is_public BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    $db->exec($sql);

    // Hesaplamalar tablosu
    $sql = "CREATE TABLE IF NOT EXISTS calculations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        calculation_type VARCHAR(50) NOT NULL,
        input_data JSON,
        result_data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )";
    $db->exec($sql);

    // Malzemeler tablosu
    $sql = "CREATE TABLE IF NOT EXISTS materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        type VARCHAR(50) NOT NULL,
        thermal_conductivity DECIMAL(8,4),
        density DECIMAL(8,2),
        specific_heat DECIMAL(8,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($sql);

    // Yapı elemanları tablosu
    $sql = "CREATE TABLE IF NOT EXISTS building_elements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        element_type VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        area DECIMAL(10,2),
        u_value DECIMAL(8,4),
        layers JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    )";
    $db->exec($sql);
}

// Tabloları oluştur
try {
    createTables();
} catch (Exception $e) {
    logError("Tablo oluşturma hatası: " . $e->getMessage());
}
?>
