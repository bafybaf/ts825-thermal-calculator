# BONUS TS 825 Hesap Programı

Web tabanlı TS 825:2024 Revizyonu esas alınarak geliştirilmiş enerji verimliliği hesap programı - Kullanıcı Üyelik Sistemi ile.

## 🔐 Kullanıcı Sistemi

### Demo Hesaplar
- **Admin:** `admin` / `admin123`
- **Kullanıcı:** `demo` / `demo123`

### Güvenlik Özellikleri
- ✅ Kullanıcı kayıt/giriş sistemi
- ✅ Session tabanlı kimlik doğrulama
- ✅ Kullanıcı bazlı proje yönetimi
- ✅ Admin panel (geliştiriliyor)
- ✅ Güvenli şifre saklama (bcrypt)
- ✅ Session timeout kontrolü

## 🏗️ Proje Özellikleri

### ✅ Ana Modüller
- **Proje Yönetimi**: Yeni proje oluşturma ve yönetme
- **Yapı Bilgileri**: Temel yapı verilerinin girilmesi
- **Isı Geçirgenlik**: Pencere, duvar, çatı hesapları
- **Isı Köprüleri**: Isı köprüsü analizi ve kontrolü
- **Yoğuşma Kontrolü**: Yoğuşma riski değerlendirmesi
- **Raporlama**: Otomatik çizelge ve rapor oluşturma

### 🛠️ Teknolojiler
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+
- **AJAX**: Dinamik veri işleme
- **Responsive**: Mobil uyumlu tasarım

## 🚀 Hızlı Kurulum

### Otomatik Kurulum (Önerilen)
1. **Dosyaları Yükleyin:** Tüm dosyaları web sunucunuza yükleyin
2. **Konfigürasyon:** `api/config.php` dosyasında veritabanı ayarlarını yapın
3. **Kurulum Sayfası:** `http://yoursite.com/install.html` adresini açın
4. **"Kurulumu Başlat"** butonuna tıklayın
5. **Giriş Yapın:** Demo hesaplarla giriş yapın

### Manuel Kurulum
1. **Dosyaları Yükleyin**
```bash
# Dosyaları web sunucunuza yükleyin (htdocs, www, public_html vb.)
```

2. **Veritabanı Oluşturun**
```sql
CREATE DATABASE ts825_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. **Konfigürasyon**
`api/config.php` dosyasında veritabanı ayarları zaten yapılandırılmış:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bonusyalitim_ts825');
define('DB_USER', 'bonusyalitim_ts825');
define('DB_PASS', 'ts825_2025');
```

4. **Veritabanı Kurulumu**
```
POST http://yoursite.com/api/install.php?action=install
```

5. **Uygulamayı Başlatın**
```
http://yoursite.com/login.html
```

## 🔧 Gereksinimler

- **Web Server:** Apache/Nginx
- **PHP:** 8.0 veya üzeri
- **MySQL:** 5.7 veya üzeri
- **Extensions:** PDO, JSON, OpenSSL

## 📱 Kullanım

1. `login.html` sayfasından giriş yapın
2. Demo hesapları kullanabilir veya yeni hesap oluşturabilirsiniz
3. Projeler oluşturun ve hesaplamalar yapın
4. Raporları PDF/Excel formatında indirin

## 🛡️ Güvenlik

- Şifreler bcrypt ile hashlenir
- Session tabanlı kimlik doğrulama
- SQL injection koruması (PDO)
- XSS koruması
- CSRF token sistemi (geliştiriliyor)

Bu program İZODER TS 825 programına alternatif olarak geliştirilmiştir ve TS 825:2024 standartlarına uygun hesaplamalar yapar.
