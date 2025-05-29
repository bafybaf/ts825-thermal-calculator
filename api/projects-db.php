<?php
/**
 * BONUS TS 825 - Gerçek Veritabanı Projects API
 */

require_once 'config.php';

// CORS headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// JSON response fonksiyonu
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Hata loglama
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - Projects DB API: " . $message . PHP_EOL, 3, '../logs/php_errors.log');
}

// Yardımcı fonksiyonlar
function getBuildingTypeName($type) {
    $types = [
        'residential' => 'Konut',
        'office' => 'Ofis',
        'commercial' => 'Ticari',
        'educational' => 'Eğitim',
        'healthcare' => 'Sağlık',
        'industrial' => 'Endüstriyel',
        'other' => 'Diğer'
    ];
    return $types[$type] ?? 'Bilinmiyor';
}

function getClimateZoneName($zone) {
    $zones = [
        1 => '1. Bölge (En Soğuk)',
        2 => '2. Bölge (Çok Soğuk)',
        3 => '3. Bölge (Soğuk)',
        4 => '4. Bölge (Ilık)',
        5 => '5. Bölge (Sıcak)',
        6 => '6. Bölge (En Sıcak)'
    ];
    return $zones[$zone] ?? 'Bilinmiyor';
}

function getStatusName($status) {
    $statuses = [
        'draft' => 'Taslak',
        'in_progress' => 'Devam Ediyor',
        'completed' => 'Tamamlandı'
    ];
    return $statuses[$status] ?? 'Bilinmiyor';
}

function generateProjectCode($buildingType) {
    $prefix = [
        'residential' => 'KNT',
        'office' => 'OFS',
        'commercial' => 'TCR',
        'educational' => 'EGT',
        'healthcare' => 'SGL',
        'industrial' => 'END',
        'other' => 'DGR'
    ];
    
    $typePrefix = $prefix[$buildingType] ?? 'TST';
    $year = date('Y');
    $timestamp = time();
    
    return $typePrefix . '-' . $year . '-' . substr($timestamp, -6);
}

class ProjectsDBAPI {
    private $pdo;

    public function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
            logError("Veritabanı bağlantısı başarılı");
        } catch (PDOException $e) {
            logError("Veritabanı bağlantı hatası: " . $e->getMessage());
            sendResponse(['success' => false, 'error' => 'Veritabanı bağlantısı kurulamadı'], 500);
        }
    }

    public function getStats() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(total_area) as total_area
                    FROM projects";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch();

            return [
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
                'source' => 'database'
            ];

        } catch (PDOException $e) {
            logError("İstatistik hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'İstatistikler yüklenemedi: ' . $e->getMessage()];
        }
    }

    public function getProjects($limit = 10, $offset = 0) {
        try {
            $sql = "SELECT * FROM projects ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit, $offset]);
            $projects = $stmt->fetchAll();

            // Formatla
            foreach ($projects as &$project) {
                $project['building_type_name'] = getBuildingTypeName($project['building_type']);
                $project['climate_zone_name'] = getClimateZoneName($project['climate_zone']);
                $project['status_name'] = getStatusName($project['status']);
                $project['created_at_formatted'] = date('d.m.Y H:i', strtotime($project['created_at']));
                $project['updated_at_formatted'] = date('d.m.Y H:i', strtotime($project['updated_at']));
            }

            return [
                'success' => true,
                'data' => $projects,
                'count' => count($projects),
                'source' => 'database'
            ];

        } catch (PDOException $e) {
            logError("Proje listesi hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Projeler yüklenemedi: ' . $e->getMessage()];
        }
    }

    public function createProject($data) {
        try {
            // Validasyon
            if (empty($data['name'])) {
                return ['success' => false, 'error' => 'Proje adı gerekli'];
            }

            // Proje kodu oluştur
            $projectCode = generateProjectCode($data['building_type'] ?? 'other');

            $sql = "INSERT INTO projects (name, description, building_type, climate_zone, total_area, project_code, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 'draft', NOW(), NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['building_type'] ?? 'other',
                (int)($data['climate_zone'] ?? 3),
                (float)($data['total_area'] ?? 0),
                $projectCode
            ]);

            $projectId = $this->pdo->lastInsertId();

            // Oluşturulan projeyi getir
            $stmt = $this->pdo->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch();

            if ($project) {
                $project['building_type_name'] = getBuildingTypeName($project['building_type']);
                $project['climate_zone_name'] = getClimateZoneName($project['climate_zone']);
                $project['status_name'] = getStatusName($project['status']);
                $project['created_at_formatted'] = date('d.m.Y H:i', strtotime($project['created_at']));
                $project['updated_at_formatted'] = date('d.m.Y H:i', strtotime($project['updated_at']));
            }

            return [
                'success' => true,
                'message' => 'Proje başarıyla oluşturuldu',
                'project_id' => $projectId,
                'project_code' => $projectCode,
                'data' => $project,
                'source' => 'database'
            ];

        } catch (PDOException $e) {
            logError("Proje oluşturma hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Proje oluşturulamadı: ' . $e->getMessage()];
        }
    }

    public function getProject($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            $project = $stmt->fetch();

            if (!$project) {
                return ['success' => false, 'error' => 'Proje bulunamadı'];
            }

            $project['building_type_name'] = getBuildingTypeName($project['building_type']);
            $project['climate_zone_name'] = getClimateZoneName($project['climate_zone']);
            $project['status_name'] = getStatusName($project['status']);
            $project['created_at_formatted'] = date('d.m.Y H:i', strtotime($project['created_at']));
            $project['updated_at_formatted'] = date('d.m.Y H:i', strtotime($project['updated_at']));

            return [
                'success' => true,
                'data' => $project,
                'source' => 'database'
            ];

        } catch (PDOException $e) {
            logError("Proje detay hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Proje detayları yüklenemedi: ' . $e->getMessage()];
        }
    }

    public function updateProject($id, $data) {
        try {
            // Proje var mı kontrol et
            $stmt = $this->pdo->prepare("SELECT id FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'error' => 'Proje bulunamadı'];
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
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['building_type'] ?? 'other',
                (int)($data['climate_zone'] ?? 3),
                (float)($data['total_area'] ?? 0),
                $data['status'] ?? 'draft',
                $id
            ]);

            return [
                'success' => true,
                'message' => 'Proje başarıyla güncellendi',
                'source' => 'database'
            ];

        } catch (PDOException $e) {
            logError("Proje güncelleme hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Proje güncellenemedi: ' . $e->getMessage()];
        }
    }

    public function deleteProject($id) {
        try {
            // Proje var mı kontrol et
            $stmt = $this->pdo->prepare("SELECT id FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'error' => 'Proje bulunamadı'];
            }

            $stmt = $this->pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$id]);

            return [
                'success' => true,
                'message' => 'Proje başarıyla silindi',
                'source' => 'database'
            ];

        } catch (PDOException $e) {
            logError("Proje silme hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Proje silinemedi: ' . $e->getMessage()];
        }
    }
}

// API endpoint handling
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $api = new ProjectsDBAPI();

    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'stats':
                    $result = $api->getStats();
                    sendResponse($result);
                    break;
                    
                case 'list':
                    $limit = (int)($_GET['limit'] ?? 10);
                    $page = (int)($_GET['page'] ?? 1);
                    $offset = ($page - 1) * $limit;
                    $result = $api->getProjects($limit, $offset);
                    sendResponse($result);
                    break;
                    
                case 'detail':
                    $id = (int)($_GET['id'] ?? 0);
                    if (!$id) {
                        sendResponse(['success' => false, 'error' => 'Proje ID gerekli'], 400);
                    }
                    $result = $api->getProject($id);
                    sendResponse($result);
                    break;
                    
                default:
                    sendResponse(['success' => false, 'error' => 'Geçersiz GET işlemi: ' . $action], 400);
            }
            break;
            
        case 'POST':
            switch ($action) {
                case 'create':
                    $input = json_decode(file_get_contents('php://input'), true);
                    if (!$input) {
                        sendResponse(['success' => false, 'error' => 'Geçersiz JSON verisi'], 400);
                    }
                    $result = $api->createProject($input);
                    sendResponse($result);
                    break;
                    
                default:
                    sendResponse(['success' => false, 'error' => 'Geçersiz POST işlemi: ' . $action], 400);
            }
            break;
            
        case 'PUT':
            switch ($action) {
                case 'update':
                    $id = (int)($_GET['id'] ?? 0);
                    if (!$id) {
                        sendResponse(['success' => false, 'error' => 'Proje ID gerekli'], 400);
                    }
                    $input = json_decode(file_get_contents('php://input'), true);
                    if (!$input) {
                        sendResponse(['success' => false, 'error' => 'Geçersiz JSON verisi'], 400);
                    }
                    $result = $api->updateProject($id, $input);
                    sendResponse($result);
                    break;
                    
                default:
                    sendResponse(['success' => false, 'error' => 'Geçersiz PUT işlemi: ' . $action], 400);
            }
            break;
            
        case 'DELETE':
            switch ($action) {
                case 'delete':
                    $id = (int)($_GET['id'] ?? 0);
                    if (!$id) {
                        sendResponse(['success' => false, 'error' => 'Proje ID gerekli'], 400);
                    }
                    $result = $api->deleteProject($id);
                    sendResponse($result);
                    break;
                    
                default:
                    sendResponse(['success' => false, 'error' => 'Geçersiz DELETE işlemi: ' . $action], 400);
            }
            break;
            
        default:
            sendResponse(['success' => false, 'error' => 'Desteklenmeyen HTTP metodu: ' . $method], 405);
    }

} catch (Exception $e) {
    logError("Projects DB API hatası: " . $e->getMessage());
    sendResponse(['success' => false, 'error' => 'Sunucu hatası: ' . $e->getMessage()], 500);
}
?>
