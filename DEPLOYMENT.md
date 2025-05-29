# 🚀 BONUS TS 825 - Production Deployment Rehberi

## 📋 Ön Gereksinimler

### Sunucu Gereksinimleri
- **PHP:** 7.4+ (8.0+ önerilen)
- **MySQL:** 5.7+ (8.0+ önerilen)
- **Web Server:** Apache/Nginx
- **SSL Sertifikası:** Önerilen

### Veritabanı Bilgileri
```
Host: localhost
Database: bonusyalitim_ts825
Username: bonusyalitim_ts825
Password: ts825_2025
Charset: utf8mb4
```

## 🔧 Kurulum Adımları

### 1. Dosyaları Yükleyin
```bash
# FTP/SFTP ile tüm dosyaları sunucuya yükleyin
# Dizin yapısı:
/public_html/
├── tse825/
│   ├── api/
│   ├── assets/
│   ├── logs/
│   ├── uploads/
│   ├── index.html
│   ├── login.html
│   ├── install.html
│   └── .htaccess
```

### 2. Klasör İzinleri
```bash
# Logs klasörü yazılabilir olmalı
chmod 755 logs/
chmod 644 logs/.htaccess

# Uploads klasörü yazılabilir olmalı
chmod 755 uploads/

# API dosyaları çalıştırılabilir olmalı
chmod 644 api/*.php
```

### 3. Veritabanı Kurulumu
1. **Otomatik Kurulum (Önerilen):**
   ```
   https://yourdomain.com/tse825/install.html
   ```

2. **Manuel Kurulum:**
   ```sql
   CREATE DATABASE bonusyalitim_ts825 
   CHARACTER SET utf8mb4 
   COLLATE utf8mb4_unicode_ci;
   
   CREATE USER 'bonusyalitim_ts825'@'localhost' 
   IDENTIFIED BY 'ts825_2025';
   
   GRANT ALL PRIVILEGES ON bonusyalitim_ts825.* 
   TO 'bonusyalitim_ts825'@'localhost';
   
   FLUSH PRIVILEGES;
   ```

### 4. Konfigürasyon Kontrolü
`api/config.php` dosyasında ayarlar zaten yapılandırılmış:
- Veritabanı bilgileri ✅
- Güvenlik anahtarı ✅
- Hata raporlama (production mode) ✅
- Session timeout (2 saat) ✅

## 🔐 Güvenlik Ayarları

### SSL Sertifikası
```apache
# .htaccess dosyasında HTTPS yönlendirmesi aktif edin:
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### Dosya İzinleri
```bash
# Güvenlik için önerilen izinler:
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type f -name "*.html" -exec chmod 644 {} \;
find . -type f -name "*.css" -exec chmod 644 {} \;
find . -type f -name "*.js" -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
```

### Backup Ayarları
```bash
# Günlük veritabanı yedeği
mysqldump -u bonusyalitim_ts825 -p bonusyalitim_ts825 > backup_$(date +%Y%m%d).sql

# Dosya yedeği
tar -czf tse825_backup_$(date +%Y%m%d).tar.gz tse825/
```

## 🎯 Demo Hesaplar

Kurulum sonrası kullanılabilir hesaplar:

| Rol | Kullanıcı Adı | Şifre | Açıklama |
|-----|---------------|-------|----------|
| Admin | admin | admin123 | Sistem yöneticisi |
| Kullanıcı | demo | demo123 | Demo kullanıcı |
| Mimar | mimar1 | mimar123 | Mimar hesabı |
| Mühendis | muhendis1 | muhendis123 | Mühendis hesabı |

## 📊 Monitoring

### Log Dosyaları
- **PHP Hataları:** `logs/php_errors.log`
- **Uygulama Logları:** `logs/app.log`
- **Aktivite Logları:** Veritabanında `activity_logs` tablosu

### Performans İzleme
```sql
-- Aktif kullanıcılar
SELECT COUNT(*) FROM user_sessions WHERE expires_at > NOW();

-- Toplam projeler
SELECT COUNT(*) FROM projects;

-- Son aktiviteler
SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10;
```

## 🔄 Güncelleme

### Versiyon Güncelleme
1. Mevcut dosyaları yedekleyin
2. Yeni dosyaları yükleyin
3. Veritabanı şemasını kontrol edin
4. Cache'i temizleyin

### Veritabanı Migrasyonu
```sql
-- Versiyon kontrolü
SELECT setting_value FROM system_settings WHERE setting_key = 'app_version';

-- Güncelleme gerekirse
UPDATE system_settings SET setting_value = '1.1.0' WHERE setting_key = 'app_version';
```

## 🆘 Sorun Giderme

### Yaygın Sorunlar

**1. Veritabanı Bağlantı Hatası:**
- Kullanıcı adı/şifre kontrolü
- Host bilgisi kontrolü
- MySQL servis durumu

**2. Dosya İzin Hataları:**
```bash
chmod 755 logs/
chmod 755 uploads/
```

**3. SSL Sertifika Sorunları:**
- Let's Encrypt kullanın
- .htaccess HTTPS yönlendirmesi

**4. Performance Sorunları:**
- MySQL query cache aktif edin
- PHP OPcache kullanın
- Gzip sıkıştırma aktif

### Destek İletişim
- **Email:** support@bonusyalitim.com
- **Telefon:** +90 XXX XXX XX XX
- **Dokümantasyon:** README.md

## ✅ Kurulum Kontrol Listesi

- [ ] Dosyalar yüklendi
- [ ] Klasör izinleri ayarlandı
- [ ] Veritabanı oluşturuldu
- [ ] Kurulum tamamlandı
- [ ] SSL sertifikası kuruldu
- [ ] Backup sistemi ayarlandı
- [ ] Demo hesaplar test edildi
- [ ] Log dosyaları kontrol edildi
- [ ] Performance test yapıldı

**🎉 Kurulum Tamamlandı!**

Sistem artık production ortamında kullanıma hazır.
