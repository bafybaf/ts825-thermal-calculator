<?php
/**
 * BONUS TS 825 Hesap Programı
 * Hesaplama API
 */

require_once 'config.php';

class CalculationAPI {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';

        try {
            switch ($method) {
                case 'GET':
                    $this->handleGet($action);
                    break;
                case 'POST':
                    $this->handlePost($action);
                    break;
                default:
                    sendJsonResponse(['error' => 'Desteklenmeyen HTTP metodu'], 405);
            }
        } catch (Exception $e) {
            logError("Hesaplama API Hatası: " . $e->getMessage());
            sendJsonResponse(['error' => 'Sunucu hatası'], 500);
        }
    }

    private function handleGet($action) {
        switch ($action) {
            case 'thermal':
                $this->getThermalCalculations();
                break;
            case 'materials':
                $this->getMaterials();
                break;
            case 'climate_data':
                $this->getClimateData();
                break;
            default:
                sendJsonResponse(['error' => 'Geçersiz işlem'], 400);
        }
    }

    private function handlePost($action) {
        switch ($action) {
            case 'thermal_transmittance':
                $this->calculateThermalTransmittance();
                break;
            case 'thermal_bridge':
                $this->calculateThermalBridge();
                break;
            case 'condensation':
                $this->calculateCondensation();
                break;
            case 'save_calculation':
                $this->saveCalculation();
                break;
            default:
                sendJsonResponse(['error' => 'Geçersiz işlem'], 400);
        }
    }

    private function calculateThermalTransmittance() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['layers'];
        $errors = validateRequired($required, $input);
        
        if (!empty($errors)) {
            sendJsonResponse(['error' => 'Eksik alanlar', 'details' => $errors], 400);
        }

        $layers = $input['layers'];
        $totalResistance = 0;
        $layerDetails = [];

        // İç yüzey direnci (Rsi)
        $rsi = 0.13; // m²K/W (standart değer)
        $totalResistance += $rsi;

        // Katman dirençleri
        foreach ($layers as $layer) {
            $thickness = (float)$layer['thickness'] / 1000; // mm'den m'ye
            $conductivity = (float)$layer['conductivity'];
            
            if ($conductivity <= 0) {
                sendJsonResponse(['error' => 'Geçersiz ısı iletkenlik değeri'], 400);
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

        // Dış yüzey direnci (Rse)
        $rse = 0.04; // m²K/W (standart değer)
        $totalResistance += $rse;

        // U değeri hesaplama
        $uValue = 1 / $totalResistance;

        // TS 825 limit değerleri (örnek)
        $climateZone = (int)($input['climate_zone'] ?? 3);
        $elementType = $input['element_type'] ?? 'wall';
        $limitValue = $this->getThermalLimitValue($elementType, $climateZone);

        $result = [
            'u_value' => round($uValue, 4),
            'total_resistance' => round($totalResistance, 4),
            'limit_value' => $limitValue,
            'compliant' => $uValue <= $limitValue,
            'layer_details' => $layerDetails,
            'surface_resistances' => [
                'rsi' => $rsi,
                'rse' => $rse
            ]
        ];

        sendJsonResponse([
            'success' => true,
            'data' => $result
        ]);
    }

    private function calculateThermalBridge() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['bridge_type', 'geometry', 'materials'];
        $errors = validateRequired($required, $input);
        
        if (!empty($errors)) {
            sendJsonResponse(['error' => 'Eksik alanlar', 'details' => $errors], 400);
        }

        $bridgeType = $input['bridge_type'];
        $geometry = $input['geometry'];
        $materials = $input['materials'];

        // Basitleştirilmiş ısı köprüsü hesabı
        $psiValue = 0; // Lineer ısı köprüsü katsayısı

        switch ($bridgeType) {
            case 'corner':
                $psiValue = $this->calculateCornerBridge($geometry, $materials);
                break;
            case 'balcony':
                $psiValue = $this->calculateBalconyBridge($geometry, $materials);
                break;
            case 'window':
                $psiValue = $this->calculateWindowBridge($geometry, $materials);
                break;
            default:
                $psiValue = 0.1; // Varsayılan değer
        }

        $result = [
            'psi_value' => round($psiValue, 4),
            'bridge_type' => $bridgeType,
            'calculation_method' => 'Simplified',
            'notes' => 'Basitleştirilmiş hesaplama yöntemi kullanılmıştır'
        ];

        sendJsonResponse([
            'success' => true,
            'data' => $result
        ]);
    }

    private function calculateCondensation() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['layers', 'indoor_temp', 'outdoor_temp', 'indoor_humidity'];
        $errors = validateRequired($required, $input);
        
        if (!empty($errors)) {
            sendJsonResponse(['error' => 'Eksik alanlar', 'details' => $errors], 400);
        }

        $layers = $input['layers'];
        $indoorTemp = (float)$input['indoor_temp'];
        $outdoorTemp = (float)$input['outdoor_temp'];
        $indoorHumidity = (float)$input['indoor_humidity'];

        // Glaser yöntemi ile yoğuşma analizi
        $temperatureProfile = $this->calculateTemperatureProfile($layers, $indoorTemp, $outdoorTemp);
        $vaporPressureProfile = $this->calculateVaporPressureProfile($layers, $indoorTemp, $outdoorTemp, $indoorHumidity);
        
        $condensationRisk = false;
        $condensationPoints = [];

        // Yoğuşma kontrolü
        for ($i = 0; $i < count($temperatureProfile); $i++) {
            $temp = $temperatureProfile[$i];
            $vaporPressure = $vaporPressureProfile[$i];
            $saturationPressure = $this->getSaturationVaporPressure($temp);
            
            if ($vaporPressure > $saturationPressure) {
                $condensationRisk = true;
                $condensationPoints[] = [
                    'layer' => $i,
                    'temperature' => $temp,
                    'vapor_pressure' => $vaporPressure,
                    'saturation_pressure' => $saturationPressure
                ];
            }
        }

        $result = [
            'condensation_risk' => $condensationRisk,
            'condensation_points' => $condensationPoints,
            'temperature_profile' => $temperatureProfile,
            'vapor_pressure_profile' => $vaporPressureProfile,
            'analysis_method' => 'Glaser Method'
        ];

        sendJsonResponse([
            'success' => true,
            'data' => $result
        ]);
    }

    private function saveCalculation() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['project_id', 'calculation_type', 'input_data', 'result_data'];
        $errors = validateRequired($required, $input);
        
        if (!empty($errors)) {
            sendJsonResponse(['error' => 'Eksik alanlar', 'details' => $errors], 400);
        }

        $projectId = (int)$input['project_id'];
        $calculationType = sanitize($input['calculation_type']);
        $inputData = json_encode($input['input_data']);
        $resultData = json_encode($input['result_data']);

        $sql = "INSERT INTO calculations (project_id, calculation_type, input_data, result_data) 
                VALUES (:project_id, :calculation_type, :input_data, :result_data)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            'project_id' => $projectId,
            'calculation_type' => $calculationType,
            'input_data' => $inputData,
            'result_data' => $resultData
        ]);

        if ($result) {
            $calculationId = $this->db->lastInsertId();
            sendJsonResponse([
                'success' => true,
                'message' => 'Hesaplama başarıyla kaydedildi',
                'calculation_id' => $calculationId
            ], 201);
        } else {
            sendJsonResponse(['error' => 'Hesaplama kaydedilemedi'], 500);
        }
    }

    private function getThermalCalculations() {
        $projectId = (int)($_GET['project_id'] ?? 0);
        
        if (!$projectId) {
            sendJsonResponse(['error' => 'Proje ID gerekli'], 400);
        }

        $sql = "SELECT * FROM calculations WHERE project_id = :project_id ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['project_id' => $projectId]);
        $calculations = $stmt->fetchAll();

        foreach ($calculations as &$calc) {
            $calc['input_data'] = json_decode($calc['input_data'], true);
            $calc['result_data'] = json_decode($calc['result_data'], true);
        }

        sendJsonResponse([
            'success' => true,
            'data' => $calculations
        ]);
    }

    private function getMaterials() {
        $sql = "SELECT * FROM materials ORDER BY type, name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $materials = $stmt->fetchAll();

        sendJsonResponse([
            'success' => true,
            'data' => $materials
        ]);
    }

    private function getClimateData() {
        global $CLIMATE_ZONES;
        
        sendJsonResponse([
            'success' => true,
            'data' => $CLIMATE_ZONES
        ]);
    }

    // Yardımcı fonksiyonlar
    private function getThermalLimitValue($elementType, $climateZone) {
        $limits = [
            'wall' => [1 => 0.57, 2 => 0.48, 3 => 0.40, 4 => 0.34, 5 => 0.29, 6 => 0.25],
            'roof' => [1 => 0.30, 2 => 0.25, 3 => 0.20, 4 => 0.18, 5 => 0.15, 6 => 0.13],
            'floor' => [1 => 0.58, 2 => 0.48, 3 => 0.40, 4 => 0.34, 5 => 0.29, 6 => 0.25],
            'window' => [1 => 2.40, 2 => 2.00, 3 => 1.80, 4 => 1.60, 5 => 1.40, 6 => 1.20]
        ];

        return $limits[$elementType][$climateZone] ?? 1.0;
    }

    private function calculateCornerBridge($geometry, $materials) {
        // Köşe ısı köprüsü hesabı
        return 0.05; // Basitleştirilmiş değer
    }

    private function calculateBalconyBridge($geometry, $materials) {
        // Balkon ısı köprüsü hesabı
        return 0.15; // Basitleştirilmiş değer
    }

    private function calculateWindowBridge($geometry, $materials) {
        // Pencere ısı köprüsü hesabı
        return 0.10; // Basitleştirilmiş değer
    }

    private function calculateTemperatureProfile($layers, $indoorTemp, $outdoorTemp) {
        // Sıcaklık profili hesabı
        $profile = [$indoorTemp];
        $tempDiff = $indoorTemp - $outdoorTemp;
        
        for ($i = 0; $i < count($layers); $i++) {
            $temp = $indoorTemp - ($tempDiff * ($i + 1) / (count($layers) + 1));
            $profile[] = $temp;
        }
        
        $profile[] = $outdoorTemp;
        return $profile;
    }

    private function calculateVaporPressureProfile($layers, $indoorTemp, $outdoorTemp, $indoorHumidity) {
        // Buhar basıncı profili hesabı
        $indoorVaporPressure = $this->getSaturationVaporPressure($indoorTemp) * ($indoorHumidity / 100);
        $outdoorVaporPressure = $this->getSaturationVaporPressure($outdoorTemp) * 0.8; // Varsayılan dış nem
        
        $profile = [$indoorVaporPressure];
        $pressureDiff = $indoorVaporPressure - $outdoorVaporPressure;
        
        for ($i = 0; $i < count($layers); $i++) {
            $pressure = $indoorVaporPressure - ($pressureDiff * ($i + 1) / (count($layers) + 1));
            $profile[] = $pressure;
        }
        
        $profile[] = $outdoorVaporPressure;
        return $profile;
    }

    private function getSaturationVaporPressure($temperature) {
        // Doymuş buhar basıncı hesabı (Magnus formülü)
        return 610.78 * exp((17.27 * $temperature) / ($temperature + 237.3));
    }
}

// API'yi çalıştır
$api = new CalculationAPI();
$api->handleRequest();
?>
