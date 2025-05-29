<?php
/**
 * BONUS TS 825 - Çalışan Basit API
 */

// CORS headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'test';

// Basit proje verisi (statik)
$projects = [
    [
        'id' => 1,
        'name' => 'Demo Okul Binası',
        'description' => 'Eğitim binası projesi',
        'building_type' => 'educational',
        'building_type_name' => 'Eğitim',
        'climate_zone' => 4,
        'climate_zone_name' => '4. Bölge (Ilık)',
        'total_area' => 1200.0,
        'status' => 'completed',
        'status_name' => 'Tamamlandı',
        'project_code' => 'EGT-2024-000001',
        'created_at' => '2024-05-20 10:00:00',
        'updated_at' => '2024-05-28 16:00:00',
        'created_at_formatted' => '20.05.2024 10:00',
        'updated_at_formatted' => '28.05.2024 16:00'
    ],
    [
        'id' => 2,
        'name' => 'Konut Projesi A',
        'description' => 'Konut kompleksi',
        'building_type' => 'residential',
        'building_type_name' => 'Konut',
        'climate_zone' => 3,
        'climate_zone_name' => '3. Bölge (Soğuk)',
        'total_area' => 850.0,
        'status' => 'in_progress',
        'status_name' => 'Devam Ediyor',
        'project_code' => 'KNT-2024-000002',
        'created_at' => '2024-05-22 14:30:00',
        'updated_at' => '2024-05-28 15:30:00',
        'created_at_formatted' => '22.05.2024 14:30',
        'updated_at_formatted' => '28.05.2024 15:30'
    ],
    [
        'id' => 3,
        'name' => 'Ofis Binası B',
        'description' => 'Ticari ofis binası',
        'building_type' => 'office',
        'building_type_name' => 'Ofis',
        'climate_zone' => 2,
        'climate_zone_name' => '2. Bölge (Çok Soğuk)',
        'total_area' => 650.5,
        'status' => 'draft',
        'status_name' => 'Taslak',
        'project_code' => 'OFS-2024-000003',
        'created_at' => '2024-05-25 09:15:00',
        'updated_at' => '2024-05-28 12:45:00',
        'created_at_formatted' => '25.05.2024 09:15',
        'updated_at_formatted' => '28.05.2024 12:45'
    ]
];

if ($action === 'stats') {
    // İstatistikler
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
        'source' => 'working-simple-api',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} elseif ($action === 'list') {
    // Proje listesi
    $limit = (int)($_GET['limit'] ?? 10);
    $limitedProjects = array_slice($projects, 0, $limit);
    
    echo json_encode([
        'success' => true,
        'data' => $limitedProjects,
        'count' => count($limitedProjects),
        'total' => count($projects),
        'source' => 'working-simple-api',
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
    
    // Yeni proje ID
    $newId = count($projects) + 1;
    
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
    $projectCode = $prefix . '-' . date('Y') . '-' . str_pad($newId, 6, '0', STR_PAD_LEFT);
    
    echo json_encode([
        'success' => true,
        'message' => 'Proje başarıyla oluşturuldu',
        'project_id' => $newId,
        'project_code' => $projectCode,
        'data' => [
            'id' => $newId,
            'name' => $input['name'],
            'description' => $input['description'] ?? '',
            'building_type' => $input['building_type'] ?? 'other',
            'climate_zone' => (int)($input['climate_zone'] ?? 3),
            'total_area' => (float)($input['total_area'] ?? 0),
            'status' => 'draft',
            'status_name' => 'Taslak',
            'project_code' => $projectCode,
            'created_at' => date('Y-m-d H:i:s'),
            'created_at_formatted' => date('d.m.Y H:i')
        ],
        'source' => 'working-simple-api',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} elseif ($action === 'detail' && $method === 'GET') {
    // Proje detayı
    $id = (int)($_GET['id'] ?? 0);
    $project = null;
    
    foreach ($projects as $p) {
        if ($p['id'] == $id) {
            $project = $p;
            break;
        }
    }
    
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
        'source' => 'working-simple-api',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} else {
    // Genel API bilgisi
    echo json_encode([
        'success' => true,
        'message' => 'Working Simple API çalışıyor',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => phpversion(),
        'method' => $method,
        'action' => $action,
        'available_actions' => ['stats', 'list', 'create', 'detail'],
        'source' => 'working-simple-api'
    ]);
}
?>
