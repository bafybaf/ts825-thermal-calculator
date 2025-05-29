<?php
/**
 * BONUS TS 825 Hesap Programı
 * Demo Veri Oluşturma API
 */

require_once 'config.php';
require_once 'auth.php';

// CORS headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Session kontrolü
function requireAuth() {
    $sessionToken = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($sessionToken)) {
        sendJsonResponse(['success' => false, 'error' => 'Oturum token\'ı gerekli'], 401);
    }

    $authManager = new AuthManager();
    $result = $authManager->validateSession($sessionToken);

    if (!$result['success']) {
        sendJsonResponse(['success' => false, 'error' => 'Geçersiz oturum'], 401);
    }

    return $result['user'];
}

// Demo verilerini oluştur
function createDemoData($userId) {
    $db = Database::getInstance()->getConnection();

    try {
        // Kullanıcının mevcut verilerini temizle
        $db->exec("DELETE FROM calculations WHERE project_id IN (SELECT id FROM projects WHERE user_id = $userId)");
        $db->exec("DELETE FROM building_elements WHERE project_id IN (SELECT id FROM projects WHERE user_id = $userId)");
        $db->exec("DELETE FROM projects WHERE user_id = $userId");

        // Demo malzemeler
        $materials = [
            ['Beton', 'concrete', 1.75, 2400, 1000],
            ['Tuğla', 'brick', 0.70, 1800, 1000],
            ['EPS Yalıtım', 'insulation', 0.035, 20, 1450],
            ['XPS Yalıtım', 'insulation', 0.030, 35, 1450],
            ['Mineral Yün', 'insulation', 0.040, 100, 1030],
            ['Sıva', 'other', 0.87, 1800, 1000],
            ['Ahşap', 'wood', 0.13, 500, 1600],
            ['Çelik', 'steel', 50.0, 7850, 460],
            ['Cam', 'glass', 1.0, 2500, 750],
            ['Alçı Levha', 'other', 0.25, 900, 1000]
        ];

        $materialSql = "INSERT INTO materials (name, type, thermal_conductivity, density, specific_heat) VALUES (?, ?, ?, ?, ?)";
        $materialStmt = $db->prepare($materialSql);

        foreach ($materials as $material) {
            $materialStmt->execute($material);
        }

        // Demo projeler (user_id ile)
        $projects = [
            [
                $userId,
                'Konut Projesi A',
                'Modern konut projesi - 3 katlı villa',
                'residential',
                3,
                250.50,
                'completed',
                'KNT-2024-' . time()
            ],
            [
                $userId,
                'Ofis Binası B',
                'Ticari ofis binası - 5 katlı',
                'office',
                2,
                1200.00,
                'in_progress',
                'OFS-2024-' . (time() + 1)
            ],
            [
                $userId,
                'Okul Binası C',
                'İlkokul binası projesi',
                'educational',
                4,
                800.75,
                'draft',
                'EGT-2024-' . (time() + 2)
            ],
            [
                $userId,
                'Hastane Projesi D',
                'Özel hastane binası',
                'healthcare',
                3,
                2500.00,
                'in_progress',
                'SGL-2024-' . (time() + 3)
            ],
            [
                $userId,
                'Alışveriş Merkezi E',
                'Büyük alışveriş merkezi',
                'commercial',
                5,
                5000.00,
                'completed',
                'TCR-2024-' . (time() + 4)
            ]
        ];

        $projectSql = "INSERT INTO projects (user_id, name, description, building_type, climate_zone, total_area, status, project_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $projectStmt = $db->prepare($projectSql);

        $projectIds = [];
        foreach ($projects as $project) {
            $projectStmt->execute($project);
            $projectIds[] = $db->lastInsertId();
        }

        // Demo hesaplamalar
        $calculations = [
            [
                $projectIds[0],
                'thermal_transmittance',
                json_encode([
                    'layers' => [
                        ['name' => 'Dış Sıva', 'thickness' => 20, 'conductivity' => 0.87],
                        ['name' => 'Tuğla Duvar', 'thickness' => 190, 'conductivity' => 0.70],
                        ['name' => 'EPS Yalıtım', 'thickness' => 50, 'conductivity' => 0.035],
                        ['name' => 'İç Sıva', 'thickness' => 15, 'conductivity' => 0.87]
                    ],
                    'element_type' => 'wall',
                    'climate_zone' => 3
                ]),
                json_encode([
                    'u_value' => 0.385,
                    'total_resistance' => 2.597,
                    'limit_value' => 0.40,
                    'compliant' => true
                ])
            ],
            [
                $projectIds[1],
                'thermal_transmittance',
                json_encode([
                    'layers' => [
                        ['name' => 'Beton Döşeme', 'thickness' => 200, 'conductivity' => 1.75],
                        ['name' => 'XPS Yalıtım', 'thickness' => 80, 'conductivity' => 0.030],
                        ['name' => 'Şap', 'thickness' => 50, 'conductivity' => 1.40]
                    ],
                    'element_type' => 'floor',
                    'climate_zone' => 2
                ]),
                json_encode([
                    'u_value' => 0.345,
                    'total_resistance' => 2.897,
                    'limit_value' => 0.48,
                    'compliant' => true
                ])
            ],
            [
                $projectIds[2],
                'thermal_transmittance',
                json_encode([
                    'layers' => [
                        ['name' => 'Kiremit', 'thickness' => 15, 'conductivity' => 1.0],
                        ['name' => 'Beton Çatı', 'thickness' => 150, 'conductivity' => 1.75],
                        ['name' => 'Mineral Yün', 'thickness' => 100, 'conductivity' => 0.040],
                        ['name' => 'Alçı Levha', 'thickness' => 12, 'conductivity' => 0.25]
                    ],
                    'element_type' => 'roof',
                    'climate_zone' => 4
                ]),
                json_encode([
                    'u_value' => 0.175,
                    'total_resistance' => 5.714,
                    'limit_value' => 0.18,
                    'compliant' => true
                ])
            ]
        ];

        $calcSql = "INSERT INTO calculations (project_id, calculation_type, input_data, result_data) VALUES (?, ?, ?, ?)";
        $calcStmt = $db->prepare($calcSql);

        foreach ($calculations as $calculation) {
            $calcStmt->execute($calculation);
        }

        // Demo yapı elemanları
        $elements = [
            [$projectIds[0], 'wall', 'Dış Duvar', 120.50, 0.385, json_encode(['layers' => 4])],
            [$projectIds[0], 'window', 'Pencereler', 25.00, 1.60, json_encode(['type' => 'double_glazed'])],
            [$projectIds[1], 'floor', 'Zemin Döşeme', 240.00, 0.345, json_encode(['layers' => 3])],
            [$projectIds[1], 'roof', 'Çatı', 240.00, 0.20, json_encode(['layers' => 4])],
            [$projectIds[2], 'wall', 'Sınıf Duvarları', 180.75, 0.38, json_encode(['layers' => 4])]
        ];

        $elementSql = "INSERT INTO building_elements (project_id, element_type, name, area, u_value, layers) VALUES (?, ?, ?, ?, ?, ?)";
        $elementStmt = $db->prepare($elementSql);

        foreach ($elements as $element) {
            $elementStmt->execute($element);
        }

        return [
            'success' => true,
            'message' => 'Demo veriler başarıyla oluşturuldu',
            'data' => [
                'projects' => count($projects),
                'materials' => count($materials),
                'calculations' => count($calculations),
                'elements' => count($elements)
            ]
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Demo veri oluşturma hatası: ' . $e->getMessage()
        ];
    }
}

// API endpoint handling
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $user = requireAuth();

    switch ($method) {
        case 'POST':
            switch ($action) {
                case 'create':
                    $result = createDemoData($user['id']);
                    sendJsonResponse($result, $result['success'] ? 200 : 400);
                    break;

                default:
                    sendJsonResponse(['success' => false, 'error' => 'Geçersiz işlem'], 400);
            }
            break;

        default:
            sendJsonResponse(['success' => false, 'error' => 'Desteklenmeyen HTTP metodu'], 405);
    }

} catch (Exception $e) {
    logError("Demo data API hatası: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'error' => 'Sunucu hatası'], 500);
}
?>
