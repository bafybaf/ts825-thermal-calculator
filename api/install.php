<?php
/**
 * BONUS TS 825 Hesap Programı
 * Veritabanı Kurulum API
 */

require_once 'config.php';

class DatabaseInstaller {
    private $db;

    public function __construct() {
        try {
            // Veritabanı bağlantısı (veritabanı adı olmadan)
            $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
            $this->db = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);

            // Karakter seti ve collation'ı zorla ayarla
            $this->db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->db->exec("SET CHARACTER SET utf8mb4");
            $this->db->exec("SET character_set_connection=utf8mb4");
            $this->db->exec("SET character_set_results=utf8mb4");
            $this->db->exec("SET character_set_client=utf8mb4");
            $this->db->exec("SET collation_connection=utf8mb4_unicode_ci");

        } catch (PDOException $e) {
            throw new Exception("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
    }

    public function install($includeDemoData = true) {
        $results = [];

        try {
            // 1. Veritabanını oluştur
            $results['database'] = $this->createDatabase();

            // 2. Veritabanını seç ve karakter setini ayarla
            $this->db->exec("USE " . DB_NAME);
            $this->db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            // 3. Collation sorununu çözmek için tabloları temizle
            $this->cleanTablesForCollation();

            // 4. Tabloları oluştur
            $results['tables'] = $this->createTables();

            // 5. Demo verileri ekle
            if ($includeDemoData) {
                $results['demo_data'] = $this->insertDemoData();
            }

            // Detaylı mesajları hazırla
            $details = [];
            foreach ($results as $key => $value) {
                $details[] = $value;
            }

            return [
                'success' => true,
                'message' => 'Veritabanı kurulumu tamamlandı',
                'results' => $results,
                'details' => $details
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Kurulum hatası: ' . $e->getMessage()
            ];
        }
    }

    private function createDatabase() {
        // Veritabanını oluştur
        $dbName = DB_NAME;
        $sql = "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        $this->db->exec($sql);

        // Veritabanını seç ve karakter setini ayarla
        $this->db->exec("USE `$dbName`");
        $this->db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

        return "Veritabanı oluşturuldu: " . DB_NAME;
    }

    private function cleanTablesForCollation() {
        try {
            // Foreign key kontrollerini kapat
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");

            // Mevcut tabloları sil (collation sorunu nedeniyle)
            $tables = [
                'activity_logs', 'reports', 'thermal_bridges', 'calculations',
                'element_layers', 'building_elements', 'materials', 'projects',
                'user_sessions', 'users', 'system_settings'
            ];

            foreach ($tables as $table) {
                try {
                    $this->db->exec("DROP TABLE IF EXISTS `$table`");
                } catch (Exception $e) {
                    // Devam et
                }
            }

            // Foreign key kontrollerini aç
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");

        } catch (Exception $e) {
            // Hata durumunda devam et
        }
    }

    private function createTables() {
        $tables = [];

        // Önce mevcut tabloları kontrol et ve eksik sütunları ekle
        $this->updateExistingTables();

        // Tüm tablolar için charset ayarla
        $tableCharset = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        // Kullanıcılar tablosu
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100),
            role ENUM('user', 'admin') DEFAULT 'user',
            is_active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($sql);
        $tables[] = 'users';

        // Kullanıcı oturumları tablosu
        $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(255) UNIQUE NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_session_token (session_token),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($sql);
        $tables[] = 'user_sessions';

        // Projeler tablosu
        $sql = "CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            building_type ENUM('residential', 'office', 'commercial', 'educational', 'healthcare', 'industrial', 'other') DEFAULT 'residential',
            climate_zone TINYINT(1) CHECK (climate_zone BETWEEN 1 AND 6),
            total_area DECIMAL(10,2),
            status ENUM('draft', 'in_progress', 'completed') DEFAULT 'draft',
            is_public BOOLEAN DEFAULT FALSE,
            project_code VARCHAR(50),
            location VARCHAR(255),
            architect VARCHAR(100),
            engineer VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_building_type (building_type),
            INDEX idx_climate_zone (climate_zone)
        ) $tableCharset";
        $this->db->exec($sql);
        $tables[] = 'projects';

        // Malzemeler tablosu
        $sql = "CREATE TABLE IF NOT EXISTS materials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type ENUM('concrete', 'brick', 'wood', 'steel', 'insulation', 'glass', 'other') DEFAULT 'other',
            thermal_conductivity DECIMAL(8,4) NOT NULL COMMENT 'W/mK',
            density DECIMAL(8,2) COMMENT 'kg/m³',
            specific_heat DECIMAL(8,2) COMMENT 'J/kgK',
            description TEXT,
            is_standard BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type (type),
            INDEX idx_thermal_conductivity (thermal_conductivity)
        )";
        $this->db->exec($sql);
        $tables[] = 'materials';

        // Yapı elemanları tablosu
        $sql = "CREATE TABLE IF NOT EXISTS building_elements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            element_type ENUM('wall', 'roof', 'floor', 'window', 'door') NOT NULL,
            area DECIMAL(10,2),
            u_value DECIMAL(8,4) COMMENT 'W/m²K',
            orientation ENUM('north', 'south', 'east', 'west', 'horizontal') DEFAULT 'south',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            INDEX idx_project_id (project_id),
            INDEX idx_element_type (element_type)
        )";
        $this->db->exec($sql);
        $tables[] = 'building_elements';

        // Yapı elemanı katmanları tablosu
        $sql = "CREATE TABLE IF NOT EXISTS element_layers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            element_id INT NOT NULL,
            material_id INT NOT NULL,
            layer_order TINYINT NOT NULL,
            thickness DECIMAL(8,2) NOT NULL COMMENT 'mm',
            thermal_resistance DECIMAL(8,4) COMMENT 'm²K/W',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (element_id) REFERENCES building_elements(id) ON DELETE CASCADE,
            FOREIGN KEY (material_id) REFERENCES materials(id),
            INDEX idx_element_id (element_id),
            INDEX idx_material_id (material_id),
            INDEX idx_layer_order (layer_order)
        )";
        $this->db->exec($sql);
        $tables[] = 'element_layers';

        // Hesaplamalar tablosu
        $sql = "CREATE TABLE IF NOT EXISTS calculations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            element_id INT,
            calculation_type ENUM('thermal_transmittance', 'thermal_bridge', 'condensation', 'energy_demand') NOT NULL,
            input_data JSON,
            result_data JSON,
            u_value DECIMAL(8,4) COMMENT 'W/m²K',
            is_compliant BOOLEAN,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (element_id) REFERENCES building_elements(id) ON DELETE SET NULL,
            INDEX idx_project_id (project_id),
            INDEX idx_calculation_type (calculation_type),
            INDEX idx_created_at (created_at)
        )";
        $this->db->exec($sql);
        $tables[] = 'calculations';

        // Isı köprüleri tablosu
        $sql = "CREATE TABLE IF NOT EXISTS thermal_bridges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            bridge_type ENUM('linear', 'point') DEFAULT 'linear',
            location VARCHAR(255),
            length DECIMAL(8,2) COMMENT 'metre',
            psi_value DECIMAL(8,4) COMMENT 'W/mK',
            chi_value DECIMAL(8,4) COMMENT 'W/K',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            INDEX idx_project_id (project_id),
            INDEX idx_bridge_type (bridge_type)
        )";
        $this->db->exec($sql);
        $tables[] = 'thermal_bridges';

        // Raporlar tablosu
        $sql = "CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            user_id INT NOT NULL,
            report_type ENUM('thermal_analysis', 'energy_certificate', 'compliance_check', 'full_report') NOT NULL,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT,
            file_path VARCHAR(500),
            file_size INT,
            status ENUM('generating', 'completed', 'failed') DEFAULT 'generating',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_project_id (project_id),
            INDEX idx_user_id (user_id),
            INDEX idx_report_type (report_type),
            INDEX idx_status (status)
        )";
        $this->db->exec($sql);
        $tables[] = 'reports';

        // Sistem ayarları tablosu
        $sql = "CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            description TEXT,
            is_public BOOLEAN DEFAULT FALSE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        )";
        $this->db->exec($sql);
        $tables[] = 'system_settings';

        // Aktivite logları tablosu
        $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50),
            entity_id INT,
            details JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_entity_type (entity_type),
            INDEX idx_created_at (created_at)
        )";
        $this->db->exec($sql);
        $tables[] = 'activity_logs';

        return "Tablolar oluşturuldu: " . implode(', ', $tables);
    }

    private function updateExistingTables() {
        try {
            // Materials tablosuna eksik sütunları ekle
            $this->addColumnIfNotExists('materials', 'description', 'TEXT');
            $this->addColumnIfNotExists('materials', 'is_standard', 'BOOLEAN DEFAULT TRUE');

            // Projects tablosuna eksik sütunları ekle
            $this->addColumnIfNotExists('projects', 'project_code', 'VARCHAR(50)');
            $this->addColumnIfNotExists('projects', 'location', 'VARCHAR(255)');
            $this->addColumnIfNotExists('projects', 'architect', 'VARCHAR(100)');
            $this->addColumnIfNotExists('projects', 'engineer', 'VARCHAR(100)');

            // System_settings tablosuna eksik sütunları ekle
            $this->addColumnIfNotExists('system_settings', 'description', 'TEXT');
            $this->addColumnIfNotExists('system_settings', 'is_public', 'BOOLEAN DEFAULT FALSE');

        } catch (Exception $e) {
            // Hata durumunda devam et
            logError("Tablo güncelleme hatası: " . $e->getMessage());
        }
    }

    private function addColumnIfNotExists($table, $column, $definition) {
        try {
            // Sütun var mı kontrol et
            $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
            $result = $this->db->query($sql);

            if ($result->rowCount() == 0) {
                // Sütun yoksa ekle
                $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
                $this->db->exec($sql);
            }
        } catch (Exception $e) {
            // Tablo yoksa veya başka hata varsa devam et
            // Bu normal çünkü tablo henüz oluşturulmamış olabilir
        }
    }

    private function insertDemoData() {
        $inserted = [];

        // Demo kullanıcılar - Önce mevcut olanları kontrol et
        $users = [
            ['admin', 'admin@ts825.com', password_hash('admin123', PASSWORD_DEFAULT), 'Sistem Yöneticisi', 'admin'],
            ['demo', 'demo@ts825.com', password_hash('demo123', PASSWORD_DEFAULT), 'Demo Kullanıcı', 'user'],
            ['mimar1', 'mimar@ts825.com', password_hash('mimar123', PASSWORD_DEFAULT), 'Ahmet Mimar', 'user'],
            ['muhendis1', 'muhendis@ts825.com', password_hash('muhendis123', PASSWORD_DEFAULT), 'Fatma Mühendis', 'user']
        ];

        $insertedUsers = 0;
        foreach ($users as $user) {
            // Kullanıcı zaten var mı kontrol et
            $checkSql = "SELECT id FROM users WHERE username = ? OR email = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$user[0], $user[1]]);

            if (!$checkStmt->fetch()) {
                // Kullanıcı yoksa ekle
                $sql = "INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($user);
                $insertedUsers++;
            }
        }
        $inserted[] = $insertedUsers . ' kullanıcı eklendi (' . (count($users) - $insertedUsers) . ' zaten mevcut)';

        // Demo malzemeler (sadece temel olanlar) - Duplicate kontrolü ile
        $materials = [
            ['Betonarme', 'concrete', 2.500, 2400, 1000, 'Standart betonarme', 1],
            ['Tuğla (Delikli)', 'brick', 0.450, 1200, 1000, 'Standart delikli tuğla', 1],
            ['EPS Yalıtım', 'insulation', 0.035, 20, 1450, 'Genişletilmiş polistiren', 1],
            ['XPS Yalıtım', 'insulation', 0.030, 35, 1450, 'Ekstrüde polistiren', 1],
            ['Mineral Yün', 'insulation', 0.040, 100, 1030, 'Cam yünü/taş yünü', 1],
            ['Sıva (Çimento)', 'other', 0.870, 1800, 1000, 'Çimento esaslı sıva', 1],
            ['Sıva (Alçı)', 'other', 0.250, 900, 1000, 'Alçı esaslı sıva', 1]
        ];

        $insertedMaterials = 0;
        foreach ($materials as $material) {
            // Malzeme zaten var mı kontrol et
            $checkSql = "SELECT id FROM materials WHERE name = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$material[0]]);

            if (!$checkStmt->fetch()) {
                // Malzeme yoksa ekle - Önce sütunları kontrol et
                try {
                    $sql = "INSERT INTO materials (name, type, thermal_conductivity, density, specific_heat, description, is_standard) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($material);
                    $insertedMaterials++;
                } catch (PDOException $e) {
                    // Eğer description sütunu yoksa, onsuz dene
                    if (strpos($e->getMessage(), 'description') !== false) {
                        $sql = "INSERT INTO materials (name, type, thermal_conductivity, density, specific_heat, is_standard) VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $this->db->prepare($sql);
                        $materialWithoutDesc = [$material[0], $material[1], $material[2], $material[3], $material[4], $material[6]];
                        $stmt->execute($materialWithoutDesc);
                        $insertedMaterials++;
                    } else {
                        throw $e;
                    }
                }
            }
        }
        $inserted[] = $insertedMaterials . ' malzeme eklendi (' . (count($materials) - $insertedMaterials) . ' zaten mevcut)';

        // Demo projeler - Kullanıcı ID'lerini kontrol et
        $demoUserId = null;
        $mimarUserId = null;

        // Demo kullanıcı ID'sini bul
        $userSql = "SELECT id FROM users WHERE username = ?";
        $userStmt = $this->db->prepare($userSql);
        $userStmt->execute(['demo']);
        $demoUser = $userStmt->fetch();
        if ($demoUser) $demoUserId = $demoUser['id'];

        $userStmt->execute(['mimar1']);
        $mimarUser = $userStmt->fetch();
        if ($mimarUser) $mimarUserId = $mimarUser['id'];

        // Eğer kullanıcılar varsa projeler ekle
        if ($demoUserId && $mimarUserId) {
            $projects = [
                [$demoUserId, 'Villa Projesi A', 'Modern villa projesi - 3 katlı müstakil ev', 'residential', 3, 250.50, 'completed', 'VLA-2024-001', 'İstanbul/Beşiktaş', 'Mimar Ahmet', 'Müh. Fatma'],
                [$demoUserId, 'Ofis Binası B', 'Ticari ofis binası - 5 katlı', 'office', 2, 1200.00, 'in_progress', 'OFB-2024-002', 'Ankara/Çankaya', 'Mimar Mehmet', 'Müh. Ali'],
                [$mimarUserId, 'Okul Binası C', 'İlkokul binası projesi', 'educational', 4, 800.75, 'draft', 'OKL-2024-003', 'İzmir/Konak', 'Mimar Ayşe', 'Müh. Zeynep']
            ];

            $insertedProjects = 0;
            foreach ($projects as $project) {
                // Proje zaten var mı kontrol et
                $checkSql = "SELECT id FROM projects WHERE project_code = ?";
                $checkStmt = $this->db->prepare($checkSql);
                $checkStmt->execute([$project[7]]); // project_code

                if (!$checkStmt->fetch()) {
                    // Proje yoksa ekle
                    $sql = "INSERT INTO projects (user_id, name, description, building_type, climate_zone, total_area, status, project_code, location, architect, engineer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($project);
                    $insertedProjects++;
                }
            }
            $inserted[] = $insertedProjects . ' proje eklendi (' . (count($projects) - $insertedProjects) . ' zaten mevcut)';
        } else {
            $inserted[] = '0 proje eklendi (kullanıcılar bulunamadı)';
        }

        // Sistem ayarları - Duplicate kontrolü ile
        $settings = [
            ['app_version', '1.0.0', 'Uygulama versiyonu', 1],
            ['ts825_version', '2024', 'TS 825 standart versiyonu', 1],
            ['default_climate_zone', '3', 'Varsayılan iklim bölgesi', 1],
            ['company_name', 'BONUS TS 825', 'Şirket adı', 1]
        ];

        $insertedSettings = 0;
        foreach ($settings as $setting) {
            // Ayar zaten var mı kontrol et
            $checkSql = "SELECT id FROM system_settings WHERE setting_key = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$setting[0]]);

            if (!$checkStmt->fetch()) {
                // Ayar yoksa ekle
                $sql = "INSERT INTO system_settings (setting_key, setting_value, description, is_public) VALUES (?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($setting);
                $insertedSettings++;
            }
        }
        $inserted[] = $insertedSettings . ' sistem ayarı eklendi (' . (count($settings) - $insertedSettings) . ' zaten mevcut)';

        return "Demo veriler eklendi: " . implode(', ', $inserted);
    }

    public function checkInstallation() {
        try {
            $this->db->exec("USE " . DB_NAME);

            $tables = ['users', 'projects', 'materials', 'calculations'];
            $existing = [];

            foreach ($tables as $table) {
                $sql = "SHOW TABLES LIKE '$table'";
                $result = $this->db->query($sql);
                if ($result->rowCount() > 0) {
                    $existing[] = $table;
                }
            }

            return [
                'success' => true,
                'installed' => count($existing) === count($tables),
                'existing_tables' => $existing,
                'required_tables' => $tables
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'installed' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function cleanDatabase() {
        try {
            $this->db->exec("USE " . DB_NAME);

            // Foreign key kontrollerini kapat
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");

            // Tabloları sil
            $tables = [
                'activity_logs', 'reports', 'thermal_bridges', 'calculations',
                'element_layers', 'building_elements', 'materials', 'projects',
                'user_sessions', 'users', 'system_settings'
            ];

            $dropped = [];
            foreach ($tables as $table) {
                try {
                    $this->db->exec("DROP TABLE IF EXISTS `$table`");
                    $dropped[] = $table;
                } catch (Exception $e) {
                    // Devam et
                }
            }

            // Foreign key kontrollerini aç
            $this->db->exec("SET FOREIGN_KEY_CHECKS = 1");

            return [
                'success' => true,
                'message' => 'Veritabanı temizlendi',
                'dropped_tables' => $dropped
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Temizleme hatası: ' . $e->getMessage()
            ];
        }
    }
}

// API endpoint handling
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $installer = new DatabaseInstaller();

    switch ($method) {
        case 'POST':
            switch ($action) {
                case 'install':
                    $input = json_decode(file_get_contents('php://input'), true);
                    $includeDemoData = $input['include_demo_data'] ?? true;
                    $result = $installer->install($includeDemoData);
                    sendJsonResponse($result, $result['success'] ? 200 : 500);
                    break;

                case 'clean':
                    $result = $installer->cleanDatabase();
                    sendJsonResponse($result, $result['success'] ? 200 : 500);
                    break;

                default:
                    sendJsonResponse(['success' => false, 'error' => 'Geçersiz işlem'], 400);
            }
            break;

        case 'GET':
            switch ($action) {
                case 'check':
                    $result = $installer->checkInstallation();
                    sendJsonResponse($result);
                    break;

                default:
                    sendJsonResponse(['success' => false, 'error' => 'Geçersiz işlem'], 400);
            }
            break;

        default:
            sendJsonResponse(['success' => false, 'error' => 'Desteklenmeyen HTTP metodu'], 405);
    }

} catch (Exception $e) {
    sendJsonResponse(['success' => false, 'error' => 'Kurulum hatası: ' . $e->getMessage()], 500);
}
?>
