<?php
/**
 * BONUS TS 825 Hesap Programı
 * Basit Hesaplama API
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Hata yakalama
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'POST':
            handlePost($action);
            break;
        default:
            sendResponse(['error' => 'Desteklenmeyen HTTP metodu'], 405);
    }

} catch (Exception $e) {
    sendResponse(['error' => 'Sunucu hatası: ' . $e->getMessage()], 500);
}

function handlePost($action) {
    switch ($action) {
        case 'thermal_transmittance':
            calculateThermalTransmittance();
            break;
        default:
            sendResponse(['error' => 'Geçersiz işlem'], 400);
    }
}

function calculateThermalTransmittance() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['layers'])) {
        sendResponse(['error' => 'Geçersiz veri'], 400);
    }

    $layers = $input['layers'];
    $elementType = $input['element_type'] ?? 'wall';
    $climateZone = (int)($input['climate_zone'] ?? 3);

    // Yüzey dirençleri
    $rsi = 0.13; // İç yüzey direnci (m²K/W)
    $rse = 0.04; // Dış yüzey direnci (m²K/W)
    
    $totalResistance = $rsi + $rse;
    $layerDetails = [];

    // Katman dirençlerini hesapla
    foreach ($layers as $layer) {
        $thickness = (float)$layer['thickness'] / 1000; // mm'den m'ye
        $conductivity = (float)$layer['conductivity'];
        
        if ($conductivity <= 0) {
            sendResponse(['error' => 'Geçersiz ısı iletkenlik değeri'], 400);
        }

        $resistance = $thickness / $conductivity;
        $totalResistance += $resistance;
        
        $layerDetails[] = [
            'name' => $layer['name'],
            'thickness' => $layer['thickness'],
            'conductivity' => $conductivity,
            'resistance' => round($resistance, 4)
        ];
    }

    // U değeri hesaplama
    $uValue = 1 / $totalResistance;

    // TS 825 limit değerleri
    $limitValue = getThermalLimitValue($elementType, $climateZone);
    $compliant = $uValue <= $limitValue;

    $result = [
        'u_value' => round($uValue, 4),
        'total_resistance' => round($totalResistance, 4),
        'limit_value' => $limitValue,
        'compliant' => $compliant,
        'layer_details' => $layerDetails,
        'surface_resistances' => [
            'rsi' => $rsi,
            'rse' => $rse
        ],
        'element_type' => $elementType,
        'climate_zone' => $climateZone
    ];

    sendResponse([
        'success' => true,
        'data' => $result
    ]);
}

function getThermalLimitValue($elementType, $climateZone) {
    $limits = [
        'wall' => [1 => 0.57, 2 => 0.48, 3 => 0.40, 4 => 0.34, 5 => 0.29, 6 => 0.25],
        'roof' => [1 => 0.30, 2 => 0.25, 3 => 0.20, 4 => 0.18, 5 => 0.15, 6 => 0.13],
        'floor' => [1 => 0.58, 2 => 0.48, 3 => 0.40, 4 => 0.34, 5 => 0.29, 6 => 0.25],
        'window' => [1 => 2.40, 2 => 2.00, 3 => 1.80, 4 => 1.60, 5 => 1.40, 6 => 1.20]
    ];

    return $limits[$elementType][$climateZone] ?? 1.0;
}

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
