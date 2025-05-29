<?php
/**
 * BONUS TS 825 - Basit Veritabanı API
 */

// Hata raporlamayı aç
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

try {
    // Config dosyasını yükle
    require_once 'config.php';
    
    // Veritabanı bağlantısı
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'test';
    
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
        
        echo json_encode([
            'success' => true,
            'data' => [
                'projects' => [
                    'total' => (int)$stats['total'],
                    'draft' => (int)$stats['draft'],
                    'in_progress' => (int)$stats['in_progress'],
                    'completed' => (int)$stats['completed'],
                    'total_area' => (float)$stats['total_area']
                ],
                'calculations' => 0
            ],
            'source' => 'database-simple',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } elseif ($action === 'list') {
        // Proje listesi
        $limit = (int)($_GET['limit'] ?? 10);
        $sql = "SELECT * FROM projects ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        $projects = $stmt->fetchAll();
        
        // Building type names
        $buildingTypes = [
            'residential' => 'Konut',
            'office' => 'Ofis',
            'commercial' => 'Ticari',
            'educational' => 'Eğitim',
            'healthcare' => 'Sağlık',
            'industrial' => 'Endüstriyel',
            'other' => 'Diğer'
        ];
        
        // Climate zone names
        $climateZones = [
            1 => '1. Bölge (En Soğuk)',
            2 => '2. Bölge (Çok Soğuk)',
            3 => '3. Bölge (Soğuk)',
            4 => '4. Bölge (Ilık)',
            5 => '5. Bölge (Sıcak)',
            6 => '6. Bölge (En Sıcak)'
        ];
        
        // Status names
        $statusNames = [
            'draft' => 'Taslak',
            'in_progress' => 'Devam Ediyor',
            'completed' => 'Tamamlandı'
        ];
        
        // Formatla
        foreach ($projects as &$project) {
            $project['building_type_name'] = $buildingTypes[$project['building_type']] ?? 'Bilinmiyor';
            $project['climate_zone_name'] = $climateZones[$project['climate_zone']] ?? 'Bilinmiyor';
            $project['status_name'] = $statusNames[$project['status']] ?? 'Bilinmiyor';
            $project['created_at_formatted'] = date('d.m.Y H:i', strtotime($project['created_at']));
            $project['updated_at_formatted'] = date('d.m.Y H:i', strtotime($project['updated_at']));
        }
        
        echo json_encode([
            'success' => true,
            'data' => $projects,
            'count' => count($projects),
            'source' => 'database-simple',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } elseif ($action === 'create' && $method === 'POST') {
        // Proje oluşturma
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || empty($input['name'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Proje adı gerekli'
            ]);
            exit;
        }
        
        // Building type prefix
        $prefixes = [
            'residential' => 'KNT',
            'office' => 'OFS',
            'commercial' => 'TCR',
            'educational' => 'EGT',
            'healthcare' => 'SGL',
            'industrial' => 'END',
            'other' => 'DGR'
        ];
        
        $prefix = $prefixes[$input['building_type']] ?? 'TST';
        $projectCode = $prefix . '-' . date('Y') . '-' . str_pad(time() % 1000000, 6, '0', STR_PAD_LEFT);
        
        $sql = "INSERT INTO projects (name, description, building_type, climate_zone, total_area, project_code, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'draft', NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input['name'],
            $input['description'] ?? '',
            $input['building_type'] ?? 'other',
            (int)($input['climate_zone'] ?? 3),
            (float)($input['total_area'] ?? 0),
            $projectCode
        ]);
        
        $projectId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Proje başarıyla oluşturuldu',
            'project_id' => $projectId,
            'project_code' => $projectCode,
            'source' => 'database-simple',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } elseif ($action === 'detail' && $method === 'GET') {
        // Proje detayı
        $id = (int)($_GET['id'] ?? 0);
        
        if (!$id) {
            echo json_encode([
                'success' => false,
                'error' => 'Proje ID gerekli'
            ]);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();
        
        if (!$project) {
            echo json_encode([
                'success' => false,
                'error' => 'Proje bulunamadı'
            ]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $project,
            'source' => 'database-simple',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        // Test endpoint
        echo json_encode([
            'success' => true,
            'message' => 'DB Simple API çalışıyor',
            'method' => $method,
            'action' => $action,
            'available_actions' => ['stats', 'list', 'create', 'detail'],
            'database' => [
                'host' => DB_HOST,
                'name' => DB_NAME,
                'user' => DB_USER
            ],
            'source' => 'database-simple',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı hatası: ' . $e->getMessage(),
        'code' => $e->getCode(),
        'source' => 'database-simple'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Genel hata: ' . $e->getMessage(),
        'source' => 'database-simple'
    ]);
}
?>
