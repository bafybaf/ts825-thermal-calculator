<?php
/**
 * Ultra Basit API - Sadece Test İçin
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'test';

// Basit proje depolama (session tabanlı)
session_start();
if (!isset($_SESSION['ultra_projects'])) {
    $_SESSION['ultra_projects'] = [
        [
            'id' => 1,
            'name' => 'Demo Proje 1',
            'description' => 'İlk demo proje',
            'building_type' => 'residential',
            'building_type_name' => 'Konut',
            'climate_zone' => 3,
            'climate_zone_name' => '3. Bölge (Soğuk)',
            'total_area' => 150.5,
            'status' => 'completed',
            'status_name' => 'Tamamlandı',
            'project_code' => 'KNT-2024-000001',
            'created_at' => '2024-05-28 10:00:00',
            'updated_at' => '2024-05-28 16:00:00',
            'created_at_formatted' => '28.05.2024 10:00',
            'updated_at_formatted' => '28.05.2024 16:00'
        ],
        [
            'id' => 2,
            'name' => 'Demo Proje 2',
            'description' => 'İkinci demo proje',
            'building_type' => 'office',
            'building_type_name' => 'Ofis',
            'climate_zone' => 2,
            'climate_zone_name' => '2. Bölge (Çok Soğuk)',
            'total_area' => 300.0,
            'status' => 'in_progress',
            'status_name' => 'Devam Ediyor',
            'project_code' => 'OFS-2024-000002',
            'created_at' => '2024-05-28 11:00:00',
            'updated_at' => '2024-05-28 15:00:00',
            'created_at_formatted' => '28.05.2024 11:00',
            'updated_at_formatted' => '28.05.2024 15:00'
        ]
    ];
}

if ($action === 'stats') {
    // Dinamik istatistikler
    $projects = $_SESSION['ultra_projects'];
    $total = count($projects);
    $draft = count(array_filter($projects, fn($p) => $p['status'] === 'draft'));
    $in_progress = count(array_filter($projects, fn($p) => $p['status'] === 'in_progress'));
    $completed = count(array_filter($projects, fn($p) => $p['status'] === 'completed'));
    $total_area = array_sum(array_column($projects, 'total_area'));

    echo json_encode([
        'success' => true,
        'data' => [
            'projects' => [
                'total' => $total,
                'draft' => $draft,
                'in_progress' => $in_progress,
                'completed' => $completed,
                'total_area' => $total_area
            ],
            'calculations' => 0
        ],
        'source' => 'ultra-simple-api'
    ]);
} elseif ($action === 'list') {
    // Dinamik proje listesi
    $projects = $_SESSION['ultra_projects'];

    // Son eklenenler önce gelsin
    usort($projects, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    echo json_encode([
        'success' => true,
        'data' => $projects,
        'count' => count($projects),
        'source' => 'ultra-simple-api'
    ]);
} elseif ($action === 'create' && $method === 'POST') {
    // Proje oluşturma
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode([
            'success' => false,
            'error' => 'Geçersiz JSON verisi'
        ]);
        exit;
    }

    // Validasyon
    if (empty($input['name'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Proje adı gerekli'
        ]);
        exit;
    }

    // Yeni proje oluştur ve session'a ekle
    $newProjectId = count($_SESSION['ultra_projects']) + 1;

    // Building type'a göre proje kodu
    $buildingTypePrefix = [
        'residential' => 'KNT',
        'office' => 'OFS',
        'commercial' => 'TCR',
        'educational' => 'EGT',
        'healthcare' => 'SGL',
        'industrial' => 'END',
        'other' => 'DGR'
    ];

    $prefix = $buildingTypePrefix[$input['building_type']] ?? 'TST';
    $projectCode = $prefix . '-' . date('Y') . '-' . str_pad($newProjectId, 6, '0', STR_PAD_LEFT);

    // Building type name
    $buildingTypeNames = [
        'residential' => 'Konut',
        'office' => 'Ofis',
        'commercial' => 'Ticari',
        'educational' => 'Eğitim',
        'healthcare' => 'Sağlık',
        'industrial' => 'Endüstriyel',
        'other' => 'Diğer'
    ];

    // Climate zone name
    $climateZoneNames = [
        1 => '1. Bölge (En Soğuk)',
        2 => '2. Bölge (Çok Soğuk)',
        3 => '3. Bölge (Soğuk)',
        4 => '4. Bölge (Ilık)',
        5 => '5. Bölge (Sıcak)',
        6 => '6. Bölge (En Sıcak)'
    ];

    $climateZone = (int)($input['climate_zone'] ?? 3);
    $now = date('Y-m-d H:i:s');
    $nowFormatted = date('d.m.Y H:i');

    $newProject = [
        'id' => $newProjectId,
        'name' => $input['name'],
        'description' => $input['description'] ?? '',
        'building_type' => $input['building_type'] ?? 'other',
        'building_type_name' => $buildingTypeNames[$input['building_type']] ?? 'Diğer',
        'climate_zone' => $climateZone,
        'climate_zone_name' => $climateZoneNames[$climateZone] ?? 'Bilinmiyor',
        'total_area' => (float)($input['total_area'] ?? 0),
        'status' => 'draft',
        'status_name' => 'Taslak',
        'project_code' => $projectCode,
        'created_at' => $now,
        'updated_at' => $now,
        'created_at_formatted' => $nowFormatted,
        'updated_at_formatted' => $nowFormatted
    ];

    // Session'a ekle
    $_SESSION['ultra_projects'][] = $newProject;

    echo json_encode([
        'success' => true,
        'message' => 'Proje başarıyla oluşturuldu',
        'project_id' => $newProjectId,
        'project_code' => $projectCode,
        'data' => $newProject,
        'source' => 'ultra-simple-api'
    ]);
} elseif ($action === 'detail' && $method === 'GET') {
    // Proje detayı
    $projectId = $_GET['id'] ?? 0;

    if (!$projectId) {
        echo json_encode([
            'success' => false,
            'error' => 'Proje ID gerekli'
        ]);
        exit;
    }

    // Demo proje detayı
    $projectDetails = [
        'id' => $projectId,
        'name' => 'Demo Proje ' . $projectId,
        'description' => 'Bu bir demo proje detayıdır.',
        'building_type' => 'residential',
        'building_type_name' => 'Konut',
        'climate_zone' => 3,
        'climate_zone_name' => '3. Bölge (Soğuk)',
        'total_area' => 150.5,
        'status' => 'in_progress',
        'status_name' => 'Devam Ediyor',
        'project_code' => 'TST-2024-' . str_pad($projectId, 6, '0', STR_PAD_LEFT),
        'created_at' => '2024-05-28 10:00:00',
        'updated_at' => '2024-05-28 16:00:00',
        'created_at_formatted' => '28.05.2024 10:00',
        'updated_at_formatted' => '28.05.2024 16:00',
        'building_elements' => [
            [
                'id' => 1,
                'name' => 'Dış Duvar',
                'type' => 'wall',
                'u_value' => 0.35,
                'area' => 120.0
            ],
            [
                'id' => 2,
                'name' => 'Pencereler',
                'type' => 'window',
                'u_value' => 1.8,
                'area' => 25.0
            ]
        ],
        'calculations' => [
            [
                'id' => 1,
                'type' => 'thermal_transmittance',
                'result' => 0.35,
                'status' => 'completed',
                'created_at' => '2024-05-28 15:00:00'
            ]
        ]
    ];

    echo json_encode([
        'success' => true,
        'data' => $projectDetails,
        'source' => 'ultra-simple-api'
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Ultra Simple API çalışıyor',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => phpversion(),
        'method' => $method,
        'action' => $action,
        'actions' => ['stats', 'list', 'create']
    ]);
}
?>
