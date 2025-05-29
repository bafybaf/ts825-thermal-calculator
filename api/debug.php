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

$debug_info = array();
$debug_info['timestamp'] = date('Y-m-d H:i:s');
$debug_info['php_version'] = phpversion();
$debug_info['request_method'] = $_SERVER['REQUEST_METHOD'];
$debug_info['request_uri'] = $_SERVER['REQUEST_URI'];

// Test database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    $debug_info['database']['connection'] = 'SUCCESS';
    $debug_info['database']['host'] = $host;
    $debug_info['database']['name'] = $dbname;
    $debug_info['database']['user'] = $username;
    
    // Check if tables exist
    $tables = ['users', 'projects', 'calculations', 'reports'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            $debug_info['database']['tables'][$table]['exists'] = $exists;
            
            if ($exists) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                $count = $stmt->fetchColumn();
                $debug_info['database']['tables'][$table]['count'] = $count;
                
                // Get table structure
                $stmt = $pdo->query("DESCRIBE $table");
                $columns = $stmt->fetchAll();
                $debug_info['database']['tables'][$table]['columns'] = array_column($columns, 'Field');
            }
        } catch (Exception $e) {
            $debug_info['database']['tables'][$table]['error'] = $e->getMessage();
        }
    }
    
} catch (PDOException $e) {
    $debug_info['database']['connection'] = 'FAILED';
    $debug_info['database']['error'] = $e->getMessage();
}

// Test POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_info['post_data']['raw'] = file_get_contents('php://input');
    $debug_info['post_data']['json'] = json_decode(file_get_contents('php://input'), true);
    $debug_info['post_data']['json_error'] = json_last_error_msg();
}

// Test GET parameters
$debug_info['get_params'] = $_GET;

// Test headers
$debug_info['headers'] = getallheaders();

// Test file permissions
$debug_info['file_permissions']['api_dir'] = is_writable(__DIR__) ? 'WRITABLE' : 'READ_ONLY';
$debug_info['file_permissions']['current_file'] = is_readable(__FILE__) ? 'READABLE' : 'NOT_READABLE';

// Test specific action
$action = $_GET['action'] ?? 'debug';
$debug_info['action'] = $action;

if ($action === 'test_create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $debug_info['test_create_user']['input'] = $input;
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        if (empty($input['username']) || empty($input['email']) || empty($input['password'])) {
            throw new Exception('Missing required fields');
        }
        
        // Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() === 0) {
            throw new Exception('Users table does not exist');
        }
        
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$input['username'], $input['email']]);
        if ($stmt->fetch()) {
            throw new Exception('Username or email already exists');
        }
        
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, full_name, email, password, role, status, phone, company, title, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $input['username'],
            $input['full_name'] ?? '',
            $input['email'],
            $hashedPassword,
            $input['role'] ?? 'user',
            $input['phone'] ?? '',
            $input['company'] ?? '',
            $input['title'] ?? ''
        ]);
        
        $debug_info['test_create_user']['result'] = 'SUCCESS';
        $debug_info['test_create_user']['user_id'] = $pdo->lastInsertId();
        
    } catch (Exception $e) {
        $debug_info['test_create_user']['result'] = 'FAILED';
        $debug_info['test_create_user']['error'] = $e->getMessage();
    }
}

// Memory usage
$debug_info['memory_usage'] = memory_get_usage(true);
$debug_info['memory_peak'] = memory_get_peak_usage(true);

// Response
echo json_encode([
    'success' => true,
    'message' => 'Debug information',
    'debug' => $debug_info
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
