<?php
/**
 * BONUS TS 825 - Hosting Test Dosyası
 * Bu dosyayı hosting'e yükledikten sonra çalıştırın
 * URL: https://ts825.bonusyalitim.com.tr/hosting-test.php
 */

// Güvenlik kontrolü
if (!isset($_GET['test']) || $_GET['test'] !== 'run') {
    die('Test parametresi gerekli. URL: hosting-test.php?test=run');
}

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>TS 825 Hosting Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .test-item { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        h1 { color: #333; text-align: center; }
        h2 { color: #666; border-bottom: 2px solid #eee; padding-bottom: 5px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 BONUS TS 825 Hosting Test</h1>
        <p><strong>Test Zamanı:</strong> " . date('d.m.Y H:i:s') . "</p>
        <hr>";

// 1. PHP Versiyonu
echo "<h2>1. PHP Bilgileri</h2>";
$phpVersion = phpversion();
if (version_compare($phpVersion, '7.4.0', '>=')) {
    echo "<div class='test-item success'>✅ PHP Versiyonu: $phpVersion (Uyumlu)</div>";
} else {
    echo "<div class='test-item error'>❌ PHP Versiyonu: $phpVersion (Minimum 7.4 gerekli)</div>";
}

// 2. Gerekli PHP Eklentileri
echo "<h2>2. PHP Eklentileri</h2>";
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'session'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<div class='test-item success'>✅ $ext: Yüklü</div>";
    } else {
        echo "<div class='test-item error'>❌ $ext: Eksik</div>";
    }
}

// 3. Dosya İzinleri
echo "<h2>3. Dosya İzinleri</h2>";
$directories = ['logs', 'api'];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "<div class='test-item success'>✅ $dir/: Yazılabilir</div>";
        } else {
            echo "<div class='test-item warning'>⚠️ $dir/: Yazma izni yok</div>";
        }
    } else {
        echo "<div class='test-item error'>❌ $dir/: Klasör bulunamadı</div>";
    }
}

// 4. Veritabanı Bağlantısı
echo "<h2>4. Veritabanı Bağlantısı</h2>";
try {
    require_once 'api/config.php';
    
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    echo "<div class='test-item success'>✅ Veritabanı Bağlantısı: Başarılı</div>";
    echo "<div class='test-item info'>📊 Host: " . DB_HOST . "</div>";
    echo "<div class='test-item info'>📊 Veritabanı: " . DB_NAME . "</div>";
    echo "<div class='test-item info'>📊 Kullanıcı: " . DB_USER . "</div>";
    
    // Tablo kontrolü
    $tables = ['users', 'projects', 'building_elements', 'calculations'];
    echo "<h3>Tablo Kontrolü:</h3>";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch();
            echo "<div class='test-item success'>✅ $table: " . $result['count'] . " kayıt</div>";
        } catch (PDOException $e) {
            echo "<div class='test-item error'>❌ $table: Tablo bulunamadı</div>";
        }
    }
    
} catch (PDOException $e) {
    echo "<div class='test-item error'>❌ Veritabanı Hatası: " . $e->getMessage() . "</div>";
}

// 5. API Test
echo "<h2>5. API Test</h2>";
if (file_exists('api/test.php')) {
    echo "<div class='test-item success'>✅ api/test.php: Dosya mevcut</div>";
    
    // API çağrısı simülasyonu
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['action'] = 'status';
    
    ob_start();
    try {
        include 'api/test.php';
        $apiOutput = ob_get_clean();
        $apiData = json_decode($apiOutput, true);
        
        if ($apiData && isset($apiData['success']) && $apiData['success']) {
            echo "<div class='test-item success'>✅ API Test: Başarılı</div>";
        } else {
            echo "<div class='test-item warning'>⚠️ API Test: Beklenmeyen yanıt</div>";
            echo "<pre>" . htmlspecialchars($apiOutput) . "</pre>";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "<div class='test-item error'>❌ API Test: " . $e->getMessage() . "</div>";
    }
} else {
    echo "<div class='test-item error'>❌ api/test.php: Dosya bulunamadı</div>";
}

// 6. Sunucu Bilgileri
echo "<h2>6. Sunucu Bilgileri</h2>";
echo "<div class='test-item info'>🖥️ Sunucu: " . $_SERVER['SERVER_SOFTWARE'] . "</div>";
echo "<div class='test-item info'>🌐 Host: " . $_SERVER['HTTP_HOST'] . "</div>";
echo "<div class='test-item info'>📁 Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</div>";
echo "<div class='test-item info'>⏰ Sunucu Zamanı: " . date('d.m.Y H:i:s') . "</div>";
echo "<div class='test-item info'>🌍 Zaman Dilimi: " . date_default_timezone_get() . "</div>";

// 7. Güvenlik Kontrolleri
echo "<h2>7. Güvenlik Kontrolleri</h2>";
if (ini_get('display_errors')) {
    echo "<div class='test-item warning'>⚠️ display_errors: Açık (Production'da kapalı olmalı)</div>";
} else {
    echo "<div class='test-item success'>✅ display_errors: Kapalı</div>";
}

if (ini_get('log_errors')) {
    echo "<div class='test-item success'>✅ log_errors: Açık</div>";
} else {
    echo "<div class='test-item warning'>⚠️ log_errors: Kapalı</div>";
}

// 8. Öneriler
echo "<h2>8. Öneriler</h2>";
echo "<div class='test-item info'>
    <strong>Hosting Kurulumu Tamamlandıktan Sonra:</strong><br>
    1. Bu test dosyasını silin (güvenlik için)<br>
    2. logs/ klasörüne yazma izni verin (chmod 755)<br>
    3. .htaccess dosyalarının çalıştığını kontrol edin<br>
    4. SSL sertifikası aktif olduğunu kontrol edin<br>
    5. Ana sayfayı test edin: <a href='index.html'>index.html</a><br>
    6. API'yi test edin: <a href='test.html'>test.html</a>
</div>";

echo "
        <hr>
        <p style='text-align: center; color: #666;'>
            <strong>BONUS TS 825 Hesap Programı</strong><br>
            Test tamamlandı - " . date('d.m.Y H:i:s') . "
        </p>
    </div>
</body>
</html>";

// Test tamamlandıktan sonra kendini sil (güvenlik)
if (isset($_GET['cleanup']) && $_GET['cleanup'] === 'yes') {
    unlink(__FILE__);
    echo "<script>alert('Test dosyası silindi.');</script>";
}
?>
