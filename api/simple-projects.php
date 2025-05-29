<?php
/**
 * BONUS TS 825 Hesap Programı
 * Basit Proje API (Test için - Veritabanı ile)
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

// Basit JSON response fonksiyonu
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Hata loglama
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - Simple Projects API: " . $message . PHP_EOL, 3, '../logs/php_errors.log');
}

class SimpleProjectAPI {
    private $db;

    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            logError("Veritabanı bağlantı hatası: " . $e->getMessage());
            sendResponse(['success' => false, 'error' => 'Veritabanı bağlantısı kurulamadı'], 500);
        }
    }

    public function createDemoData() {
        try {
            // Demo projeler (user_id olmadan)
            $projects = [
                [
                    'Demo Konut Projesi',
                    'Test konut projesi açıklaması',
                    'residential',
                    3,
                    150.50,
                    'completed',
                    'KNT-2024-' . time()
                ],
                [
                    'Demo Ofis Binası',
                    'Test ofis binası açıklaması',
                    'office',
                    2,
                    800.00,
                    'in_progress',
                    'OFS-2024-' . (time() + 1)
                ],
                [
                    'Demo Okul Binası',
                    'Test okul binası açıklaması',
                    'educational',
                    4,
                    1200.75,
                    'draft',
                    'EGT-2024-' . (time() + 2)
                ]
            ];

            // Mevcut demo verileri temizle
            $this->db->exec("DELETE FROM projects WHERE name LIKE 'Demo %'");

            $sql = "INSERT INTO projects (name, description, building_type, climate_zone, total_area, status, project_code) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);

            $createdCount = 0;
            foreach ($projects as $project) {
                if ($stmt->execute($project)) {
                    $createdCount++;
                }
            }

            return [
                'success' => true,
                'message' => 'Demo veriler başarıyla oluşturuldu',
                'data' => [
                    'created_projects' => $createdCount,
                    'total_projects' => count($projects)
                ]
            ];

        } catch (PDOException $e) {
            logError("Demo veri oluşturma hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Demo veriler oluşturulamadı: ' . $e->getMessage()];
        }
    }

    public function getProjects() {
        try {
            $sql = "SELECT * FROM projects ORDER BY created_at DESC LIMIT 10";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $projects = $stmt->fetchAll();

            // Formatla
            foreach ($projects as &$project) {
                $project['building_type_name'] = $this->getBuildingTypeName($project['building_type']);
                $project['climate_zone_name'] = $this->getClimateZoneName($project['climate_zone']);
                $project['status_name'] = $this->getStatusName($project['status']);
                $project['created_at_formatted'] = date('d.m.Y H:i', strtotime($project['created_at']));
            }

            return [
                'success' => true,
                'data' => $projects,
                'count' => count($projects)
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

            $sql = "INSERT INTO projects (name, description, building_type, climate_zone, total_area, project_code, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'draft')";

            $stmt = $this->db->prepare($sql);

            // Proje kodu oluştur
            $projectCode = $this->generateProjectCode($data['building_type']);

            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['building_type'] ?? 'other',
                (int)($data['climate_zone'] ?? 3),
                (float)($data['total_area'] ?? 0),
                $projectCode
            ]);

            $projectId = $this->db->lastInsertId();

            return [
                'success' => true,
                'message' => 'Proje başarıyla oluşturuldu',
                'data' => [
                    'project_id' => $projectId,
                    'project_code' => $projectCode
                ]
            ];

        } catch (PDOException $e) {
            logError("Proje oluşturma hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Proje oluşturulamadı: ' . $e->getMessage()];
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

            $stmt = $this->db->prepare($sql);
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
                    ]
                ]
            ];

        } catch (PDOException $e) {
            logError("İstatistik hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'İstatistikler yüklenemedi: ' . $e->getMessage()];
        }
    }

    private function generateProjectCode($buildingType) {
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

    private function getBuildingTypeName($type) {
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

    private function getClimateZoneName($zone) {
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

    private function getStatusName($status) {
        $statuses = [
            'draft' => 'Taslak',
            'in_progress' => 'Devam Ediyor',
            'completed' => 'Tamamlandı'
        ];
        return $statuses[$status] ?? 'Bilinmiyor';
    }
}

// API endpoint handling
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $api = new SimpleProjectAPI();

    switch ($method) {
        case 'POST':
            switch ($action) {
                case 'create_demo':
                    $result = $api->createDemoData();
                    sendResponse($result);
                    break;

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

        case 'GET':
            switch ($action) {
                case 'list':
                    $result = $api->getProjects();
                    sendResponse($result);
                    break;

                case 'stats':
                    $result = $api->getStats();
                    sendResponse($result);
                    break;

                default:
                    sendResponse(['success' => false, 'error' => 'Geçersiz GET işlemi: ' . $action], 400);
            }
            break;

        default:
            sendResponse(['success' => false, 'error' => 'Desteklenmeyen HTTP metodu: ' . $method], 405);
    }

} catch (Exception $e) {
    logError("Simple Projects API hatası: " . $e->getMessage());
    sendResponse(['success' => false, 'error' => 'Sunucu hatası: ' . $e->getMessage()], 500);
}
?>
