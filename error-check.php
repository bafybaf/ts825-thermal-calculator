<?php
/**
 * PHP Hata Log Kontrol Dosyası
 * URL: https://ts825hesap.bonusyalitim.com.tr/error-check.php
 */

header('Content-Type: text/plain; charset=utf-8');

// Güvenlik kontrolü
if (!isset($_GET['check']) || $_GET['check'] !== 'errors') {
    die('Hata kontrol parametresi gerekli. URL: error-check.php?check=errors');
}

echo "=== PHP HATA LOG KONTROLÜ ===\n";
echo "Zaman: " . date('Y-m-d H:i:s') . "\n\n";

// 1. PHP ayarlarını kontrol et
echo "1. PHP AYARLARI:\n";
echo "display_errors: " . (ini_get('display_errors') ? 'ON' : 'OFF') . "\n";
echo "log_errors: " . (ini_get('log_errors') ? 'ON' : 'OFF') . "\n";
echo "error_log: " . ini_get('error_log') . "\n";
echo "error_reporting: " . error_reporting() . "\n\n";

// 2. Log dosyası konumlarını kontrol et
echo "2. LOG DOSYASI KONTROLLERI:\n";

$logPaths = [
    'logs/php_errors.log',
    '../logs/php_errors.log',
    '/tmp/php_errors.log',
    ini_get('error_log')
];

foreach ($logPaths as $path) {
    if (empty($path)) continue;
    
    echo "Kontrol ediliyor: $path\n";
    
    if (file_exists($path)) {
        echo "  ✅ Dosya mevcut\n";
        echo "  📏 Boyut: " . filesize($path) . " bytes\n";
        echo "  🕒 Son değişiklik: " . date('Y-m-d H:i:s', filemtime($path)) . "\n";
        
        if (is_readable($path)) {
            echo "  📖 Okunabilir: EVET\n";
            
            // Son 10 satırı oku
            $lines = file($path);
            if ($lines && count($lines) > 0) {
                echo "  📄 Son 5 satır:\n";
                $lastLines = array_slice($lines, -5);
                foreach ($lastLines as $line) {
                    echo "    " . trim($line) . "\n";
                }
            } else {
                echo "  📄 Dosya boş\n";
            }
        } else {
            echo "  ❌ Okunabilir: HAYIR\n";
        }
    } else {
        echo "  ❌ Dosya bulunamadı\n";
    }
    echo "\n";
}

// 3. API dosyalarını test et
echo "3. API DOSYA KONTROLLERI:\n";

$apiFiles = [
    'api/stats-simple.php',
    'api/list-simple.php',
    'api/config.php'
];

foreach ($apiFiles as $file) {
    echo "Kontrol ediliyor: $file\n";
    
    if (file_exists($file)) {
        echo "  ✅ Dosya mevcut\n";
        echo "  📏 Boyut: " . filesize($file) . " bytes\n";
        echo "  🔐 İzinler: " . substr(sprintf('%o', fileperms($file)), -4) . "\n";
        
        if (is_readable($file)) {
            echo "  📖 Okunabilir: EVET\n";
        } else {
            echo "  ❌ Okunabilir: HAYIR\n";
        }
    } else {
        echo "  ❌ Dosya bulunamadı\n";
    }
    echo "\n";
}

// 4. Basit PHP syntax kontrolü
echo "4. PHP SYNTAX KONTROLLERI:\n";

foreach (['api/stats-simple.php', 'api/list-simple.php'] as $file) {
    if (file_exists($file)) {
        echo "Syntax kontrol: $file\n";
        
        // PHP syntax check
        $output = [];
        $return_var = 0;
        exec("php -l $file 2>&1", $output, $return_var);
        
        if ($return_var === 0) {
            echo "  ✅ Syntax: OK\n";
        } else {
            echo "  ❌ Syntax hatası:\n";
            foreach ($output as $line) {
                echo "    $line\n";
            }
        }
    }
    echo "\n";
}

// 5. Manuel API test
echo "5. MANUEL API TEST:\n";

try {
    echo "Config dosyası test ediliyor...\n";
    require_once 'api/config.php';
    echo "  ✅ Config yüklendi\n";
    
    echo "Veritabanı bağlantısı test ediliyor...\n";
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "  ✅ Veritabanı bağlantısı başarılı\n";
    
    echo "Basit sorgu test ediliyor...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects");
    $result = $stmt->fetch();
    echo "  ✅ Proje sayısı: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "  ❌ Hata: " . $e->getMessage() . "\n";
}

echo "\n=== KONTROL TAMAMLANDI ===\n";
?>
