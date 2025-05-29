<?php
/**
 * BONUS TS 825 Hesap Programı
 * Proje Yönetimi API (Düzeltilmiş)
 */

require_once 'config.php';
require_once 'auth.php';

// CORS headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Session kontrolü
function requireAuth() {
    $sessionToken = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_AUTHORIZATION'] ?? '';

    // Header'dan Authorization al
    if (empty($sessionToken)) {
        $headers = getallheaders();
        $sessionToken = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (empty($sessionToken)) {
        sendJsonResponse(['success' => false, 'error' => 'Oturum token\'ı gerekli'], 401);
        exit;
    }

    try {
        $authManager = new AuthManager();
        $result = $authManager->validateSession($sessionToken);

        if (!$result['success']) {
            sendJsonResponse(['success' => false, 'error' => 'Geçersiz oturum'], 401);
            exit;
        }

        return $result['user'];
    } catch (Exception $e) {
        logError("Auth hatası: " . $e->getMessage());
        sendJsonResponse(['success' => false, 'error' => 'Oturum doğrulama hatası'], 401);
        exit;
    }
}

class ProjectAPI {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function createProject($data, $userId) {
        // Validasyon
        if (empty($data['name'])) {
            return ['success' => false, 'error' => 'Proje adı gerekli'];
        }

        if (empty($data['building_type'])) {
            return ['success' => false, 'error' => 'Yapı türü gerekli'];
        }

        if (empty($data['climate_zone'])) {
            return ['success' => false, 'error' => 'İklim bölgesi gerekli'];
        }

        try {
            $sql = "INSERT INTO projects (user_id, name, description, building_type, climate_zone, total_area, project_code, location, architect, engineer, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')";

            $stmt = $this->db->prepare($sql);

            // Proje kodu oluştur
            $projectCode = $this->generateProjectCode($data['building_type']);

            $stmt->execute([
                $userId,
                $data['name'],
                $data['description'] ?? '',
                $data['building_type'],
                (int)$data['climate_zone'],
                (float)($data['total_area'] ?? 0),
                $projectCode,
                $data['location'] ?? '',
                $data['architect'] ?? '',
                $data['engineer'] ?? ''
            ]);

            $projectId = $this->db->lastInsertId();

            return [
                'success' => true,
                'message' => 'Proje başarıyla oluşturuldu',
                'project_id' => $projectId,
                'project_code' => $projectCode
            ];

        } catch (PDOException $e) {
            logError("Proje oluşturma hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Proje oluşturma başarısız: ' . $e->getMessage()];
        }
    }

    public function getProjects($userId, $options = []) {
        try {
            $where = ["p.user_id = ?"];
            $params = [$userId];

            // Filtreleme
            if (!empty($options['search'])) {
                $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.project_code LIKE ?)";
                $searchTerm = '%' . $options['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            if (!empty($options['status'])) {
                $where[] = "p.status = ?";
                $params[] = $options['status'];
            }

            if (!empty($options['building_type'])) {
                $where[] = "p.building_type = ?";
                $params[] = $options['building_type'];
            }

            if (!empty($options['climate_zone'])) {
                $where[] = "p.climate_zone = ?";
                $params[] = (int)$options['climate_zone'];
            }

            // Sayfalama
            $page = (int)($options['page'] ?? 1);
            $limit = (int)($options['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            // Toplam sayı
            $countSql = "SELECT COUNT(*) as total FROM projects p WHERE " . implode(' AND ', $where);
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];

            // Projeler
            $sql = "SELECT p.* FROM projects p
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY p.updated_at DESC
                    LIMIT ? OFFSET ?";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $projects = $stmt->fetchAll();

            // Formatla
            foreach ($projects as &$project) {
                $project['building_type_name'] = $this->getBuildingTypeName($project['building_type']);
                $project['climate_zone_name'] = $this->getClimateZoneName($project['climate_zone']);
                $project['status_name'] = $this->getStatusName($project['status']);
                $project['created_at_formatted'] = date('d.m.Y H:i', strtotime($project['created_at']));
                $project['updated_at_formatted'] = date('d.m.Y H:i', strtotime($project['updated_at']));
                $project['calculation_count'] = 0;
                $project['element_count'] = 0;
            }

            return [
                'success' => true,
                'data' => $projects,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ];

        } catch (PDOException $e) {
            logError("Proje listesi hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Projeler yüklenemedi: ' . $e->getMessage()];
        }
    }

    public function getProject($projectId, $userId) {
        try {
            $sql = "SELECT p.* FROM projects p WHERE p.id = ? AND p.user_id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$projectId, $userId]);
            $project = $stmt->fetch();

            if (!$project) {
                return ['success' => false, 'error' => 'Proje bulunamadı'];
            }

            // Formatla
            $project['building_type_name'] = $this->getBuildingTypeName($project['building_type']);
            $project['climate_zone_name'] = $this->getClimateZoneName($project['climate_zone']);
            $project['status_name'] = $this->getStatusName($project['status']);
            $project['building_elements'] = [];
            $project['calculations'] = [];
            $project['thermal_bridges'] = [];
            $project['reports'] = [];

            return ['success' => true, 'data' => $project];

        } catch (PDOException $e) {
            logError("Proje detay hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Proje detayları yüklenemedi: ' . $e->getMessage()];
        }
    }

    public function deleteProject($projectId, $userId) {
        try {
            // Proje sahibi kontrolü
            $sql = "SELECT name FROM projects WHERE id = ? AND user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$projectId, $userId]);
            $project = $stmt->fetch();

            if (!$project) {
                return ['success' => false, 'error' => 'Proje bulunamadı veya yetkiniz yok'];
            }

            // Projeyi sil
            $sql = "DELETE FROM projects WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$projectId]);

            return ['success' => true, 'message' => 'Proje başarıyla silindi'];

        } catch (PDOException $e) {
            logError("Proje silme hatası: " . $e->getMessage());
            return ['success' => false, 'error' => 'Proje silinemedi: ' . $e->getMessage()];
        }
    }

    public function getProjectStats($userId) {
        try {
            $sql = "SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(total_area) as total_area
                    FROM projects
                    WHERE user_id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $stats = $stmt->fetch();

            return [
                'success' => true,
                'data' => [
                    'projects' => [
                        'total' => (int)$stats['total'],
                        'draft' => (int)$stats['draft'],
                        'in_progress' => (int)$stats['in_progress'],
                        'completed' => (int)$stats['completed'],
                        'total_area' => (float)$stats['total_area'],
                        'building_types' => 1
                    ],
                    'calculations' => 0
                ]
            ];

        } catch (PDOException $e) {
            logError("Proje istatistik hatası: " . $e->getMessage());
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

        $typePrefix = $prefix[$buildingType] ?? 'PRJ';
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

// Debug için
error_log("Projects API - Method: $method, Action: $action");

try {
    $user = requireAuth();
    $projectAPI = new ProjectAPI();

    switch ($method) {
        case 'POST':
            switch ($action) {
                case 'create':
                    $input = json_decode(file_get_contents('php://input'), true);
                    if (!$input) {
                        sendJsonResponse(['success' => false, 'error' => 'Geçersiz JSON verisi'], 400);
                        exit;
                    }
                    $result = $projectAPI->createProject($input, $user['id']);
                    sendJsonResponse($result, $result['success'] ? 201 : 400);
                    break;

                default:
                    sendJsonResponse(['success' => false, 'error' => 'Geçersiz POST işlemi: ' . $action], 400);
            }
            break;

        case 'GET':
            switch ($action) {
                case 'list':
                    $options = [
                        'page' => $_GET['page'] ?? 1,
                        'limit' => $_GET['limit'] ?? 10,
                        'search' => $_GET['search'] ?? '',
                        'status' => $_GET['status'] ?? '',
                        'building_type' => $_GET['building_type'] ?? '',
                        'climate_zone' => $_GET['climate_zone'] ?? ''
                    ];
                    $result = $projectAPI->getProjects($user['id'], $options);
                    sendJsonResponse($result);
                    break;

                case 'detail':
                    $projectId = (int)($_GET['id'] ?? 0);
                    if (!$projectId) {
                        sendJsonResponse(['success' => false, 'error' => 'Proje ID gerekli'], 400);
                        exit;
                    }
                    $result = $projectAPI->getProject($projectId, $user['id']);
                    sendJsonResponse($result, $result['success'] ? 200 : 404);
                    break;

                case 'stats':
                    $result = $projectAPI->getProjectStats($user['id']);
                    sendJsonResponse($result);
                    break;

                default:
                    sendJsonResponse(['success' => false, 'error' => 'Geçersiz GET işlemi: ' . $action], 400);
            }
            break;

        case 'DELETE':
            switch ($action) {
                case 'delete':
                    $projectId = (int)($_GET['id'] ?? 0);
                    if (!$projectId) {
                        sendJsonResponse(['success' => false, 'error' => 'Proje ID gerekli'], 400);
                        exit;
                    }
                    $result = $projectAPI->deleteProject($projectId, $user['id']);
                    sendJsonResponse($result, $result['success'] ? 200 : 400);
                    break;

                default:
                    sendJsonResponse(['success' => false, 'error' => 'Geçersiz DELETE işlemi: ' . $action], 400);
            }
            break;

        default:
            sendJsonResponse(['success' => false, 'error' => 'Desteklenmeyen HTTP metodu: ' . $method], 405);
    }

} catch (Exception $e) {
    error_log("Projeler API hatası: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'error' => 'Sunucu hatası: ' . $e->getMessage()], 500);
}
?>
