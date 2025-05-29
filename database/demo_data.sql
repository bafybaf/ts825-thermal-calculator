-- BONUS TS 825 Hesap Programı
-- Demo Veriler

USE ts825_db;

-- Demo kullanıcılar
INSERT INTO users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@ts825.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sistem Yöneticisi', 'admin'),
('demo', 'demo@ts825.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo Kullanıcı', 'user'),
('mimar1', 'mimar@ts825.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ahmet Mimar', 'user'),
('muhendis1', 'muhendis@ts825.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Fatma Mühendis', 'user');

-- Standart malzemeler
INSERT INTO materials (name, type, thermal_conductivity, density, specific_heat, description, is_standard) VALUES
-- Beton ve Taş Malzemeler
('Betonarme', 'concrete', 2.500, 2400, 1000, 'Standart betonarme', TRUE),
('Hafif Beton', 'concrete', 1.000, 1600, 1000, 'Hafif agrega betonu', TRUE),
('Gazbeton (AAC)', 'concrete', 0.160, 600, 1000, 'Otoklavlı gaz beton', TRUE),
('Tuğla (Delikli)', 'brick', 0.450, 1200, 1000, 'Standart delikli tuğla', TRUE),
('Tuğla (Dolgulu)', 'brick', 0.700, 1800, 1000, 'Dolgulu tuğla duvar', TRUE),

-- Yalıtım Malzemeleri
('EPS Yalıtım', 'insulation', 0.035, 20, 1450, 'Genişletilmiş polistiren', TRUE),
('XPS Yalıtım', 'insulation', 0.030, 35, 1450, 'Ekstrüde polistiren', TRUE),
('Mineral Yün', 'insulation', 0.040, 100, 1030, 'Cam yünü/taş yünü', TRUE),
('Poliüretan Köpük', 'insulation', 0.025, 40, 1400, 'PU köpük yalıtım', TRUE),
('Fenol Köpük', 'insulation', 0.022, 40, 1400, 'Fenol köpük yalıtım', TRUE),

-- Ahşap Malzemeler
('Çam Ahşabı', 'wood', 0.130, 500, 1600, 'Yumuşak ahşap', TRUE),
('Meşe Ahşabı', 'wood', 0.180, 700, 1600, 'Sert ahşap', TRUE),
('Kontrplak', 'wood', 0.150, 600, 1600, 'Çok katmanlı ahşap', TRUE),

-- Metal Malzemeler
('Çelik', 'steel', 50.000, 7850, 460, 'Yapısal çelik', TRUE),
('Alüminyum', 'steel', 230.000, 2700, 880, 'Alüminyum alaşım', TRUE),

-- Cam ve Diğer
('Cam (Tek)', 'glass', 1.000, 2500, 750, 'Tek cam', TRUE),
('Cam (Çift)', 'glass', 2.800, 2500, 750, 'Çift cam sistem', TRUE),
('Sıva (Çimento)', 'other', 0.870, 1800, 1000, 'Çimento esaslı sıva', TRUE),
('Sıva (Alçı)', 'other', 0.250, 900, 1000, 'Alçı esaslı sıva', TRUE),
('Membran', 'other', 0.170, 1200, 1000, 'Su yalıtım membranı', TRUE);

-- Demo projeler
INSERT INTO projects (user_id, name, description, building_type, climate_zone, total_area, status, project_code, location, architect, engineer) VALUES
(2, 'Villa Projesi A', 'Modern villa projesi - 3 katlı müstakil ev', 'residential', 3, 250.50, 'completed', 'VLA-2024-001', 'İstanbul/Beşiktaş', 'Mimar Ahmet', 'Müh. Fatma'),
(2, 'Ofis Binası B', 'Ticari ofis binası - 5 katlı', 'office', 2, 1200.00, 'in_progress', 'OFB-2024-002', 'Ankara/Çankaya', 'Mimar Mehmet', 'Müh. Ali'),
(3, 'Okul Binası C', 'İlkokul binası projesi', 'educational', 4, 800.75, 'draft', 'OKL-2024-003', 'İzmir/Konak', 'Mimar Ayşe', 'Müh. Zeynep'),
(3, 'Hastane Projesi D', 'Özel hastane binası', 'healthcare', 3, 2500.00, 'in_progress', 'HST-2024-004', 'Bursa/Nilüfer', 'Mimar Can', 'Müh. Deniz'),
(4, 'AVM Projesi E', 'Büyük alışveriş merkezi', 'commercial', 5, 5000.00, 'completed', 'AVM-2024-005', 'Antalya/Muratpaşa', 'Mimar Ece', 'Müh. Berk');

-- Demo yapı elemanları
INSERT INTO building_elements (project_id, name, element_type, area, u_value, orientation, description) VALUES
-- Villa Projesi A
(1, 'Dış Duvar - Güney', 'wall', 45.50, 0.385, 'south', 'Ana dış duvar sistemi'),
(1, 'Dış Duvar - Kuzey', 'wall', 42.30, 0.385, 'north', 'Kuzey cephe duvarı'),
(1, 'Çatı Sistemi', 'roof', 85.20, 0.175, 'horizontal', 'Eğimli çatı sistemi'),
(1, 'Zemin Döşeme', 'floor', 85.20, 0.345, 'horizontal', 'Zemin üzeri döşeme'),
(1, 'Pencereler - Güney', 'window', 12.50, 1.60, 'south', 'Çift cam pencere sistemi'),

-- Ofis Binası B
(2, 'Perdeli Dış Duvar', 'wall', 180.00, 0.320, 'south', 'Cam perdeli sistem'),
(2, 'Çatı Terası', 'roof', 240.00, 0.200, 'horizontal', 'Teras çatı sistemi'),
(2, 'Bodrum Duvarı', 'wall', 60.00, 0.450, 'north', 'Bodrum dış duvarı'),

-- Okul Binası C
(3, 'Sınıf Duvarları', 'wall', 120.75, 0.380, 'south', 'Sınıf dış duvarları'),
(3, 'Spor Salonu Çatısı', 'roof', 200.00, 0.180, 'horizontal', 'Büyük açıklık çatı');

-- Demo katmanlar (Villa Projesi A - Dış Duvar)
INSERT INTO element_layers (element_id, material_id, layer_order, thickness, thermal_resistance) VALUES
-- Dış Duvar - Güney (element_id: 1)
(1, 15, 1, 20.0, 0.023),  -- Dış sıva
(1, 4, 2, 190.0, 0.422),  -- Tuğla duvar
(1, 6, 3, 50.0, 1.429),   -- EPS yalıtım
(1, 16, 4, 15.0, 0.060),  -- İç sıva

-- Çatı Sistemi (element_id: 3)
(3, 1, 1, 150.0, 0.060),  -- Betonarme döşeme
(3, 8, 2, 100.0, 2.500),  -- Mineral yün
(3, 17, 3, 5.0, 0.029);   -- Membran

-- Demo hesaplamalar
INSERT INTO calculations (project_id, element_id, calculation_type, input_data, result_data, u_value, is_compliant, notes) VALUES
(1, 1, 'thermal_transmittance', 
 '{"layers": [{"name": "Dış Sıva", "thickness": 20, "conductivity": 0.87}, {"name": "Tuğla", "thickness": 190, "conductivity": 0.45}, {"name": "EPS", "thickness": 50, "conductivity": 0.035}, {"name": "İç Sıva", "thickness": 15, "conductivity": 0.25}], "element_type": "wall", "climate_zone": 3}',
 '{"u_value": 0.385, "total_resistance": 2.597, "limit_value": 0.40, "compliant": true}',
 0.385, TRUE, 'TS 825 standartlarına uygun'),

(1, 3, 'thermal_transmittance',
 '{"layers": [{"name": "Beton", "thickness": 150, "conductivity": 2.5}, {"name": "Mineral Yün", "thickness": 100, "conductivity": 0.04}, {"name": "Membran", "thickness": 5, "conductivity": 0.17}], "element_type": "roof", "climate_zone": 3}',
 '{"u_value": 0.175, "total_resistance": 5.714, "limit_value": 0.20, "compliant": true}',
 0.175, TRUE, 'Çatı yalıtımı yeterli'),

(2, 6, 'thermal_transmittance',
 '{"layers": [{"name": "Cam Perde", "thickness": 24, "conductivity": 1.0}, {"name": "Hava Boşluğu", "thickness": 16, "conductivity": 0.025}, {"name": "Cam", "thickness": 6, "conductivity": 1.0}], "element_type": "wall", "climate_zone": 2}',
 '{"u_value": 0.320, "total_resistance": 3.125, "limit_value": 0.48, "compliant": true}',
 0.320, TRUE, 'Perdeli cephe sistemi uygun');

-- Demo ısı köprüleri
INSERT INTO thermal_bridges (project_id, name, bridge_type, location, length, psi_value, description) VALUES
(1, 'Balkon Bağlantısı', 'linear', 'Güney cephe balkon', 8.50, 0.150, 'Balkon döşeme bağlantısı'),
(1, 'Pencere Çevresi', 'linear', 'Tüm pencereler', 45.20, 0.050, 'Pencere kasası çevresi'),
(1, 'Köşe Detayı', 'linear', 'Bina köşeleri', 12.00, 0.080, 'Dış duvar köşe bağlantısı'),
(2, 'Perde Duvar Bağlantısı', 'linear', 'Döşeme seviyesi', 60.00, 0.200, 'Cam perde döşeme bağlantısı'),
(2, 'Kolon Bağlantısı', 'point', 'Yapısal kolonlar', 1.00, 0.300, 'Betonarme kolon geçişi');

-- Demo raporlar
INSERT INTO reports (project_id, user_id, report_type, title, content, status) VALUES
(1, 2, 'thermal_analysis', 'Villa Projesi A - Isı Analiz Raporu', 'Detaylı ısı geçirgenlik analizi...', 'completed'),
(1, 2, 'compliance_check', 'TS 825 Uygunluk Raporu', 'Standart uygunluk kontrolü...', 'completed'),
(2, 2, 'energy_certificate', 'Ofis Binası B - Enerji Sertifikası', 'Enerji performans değerlendirmesi...', 'completed'),
(3, 3, 'full_report', 'Okul Binası C - Tam Rapor', 'Kapsamlı proje raporu...', 'generating');

-- Sistem ayarları
INSERT INTO system_settings (setting_key, setting_value, description, is_public) VALUES
('app_version', '1.0.0', 'Uygulama versiyonu', TRUE),
('ts825_version', '2024', 'TS 825 standart versiyonu', TRUE),
('max_file_size', '10485760', 'Maksimum dosya boyutu (bytes)', FALSE),
('session_timeout', '3600', 'Oturum zaman aşımı (saniye)', FALSE),
('default_climate_zone', '3', 'Varsayılan iklim bölgesi', TRUE),
('company_name', 'BONUS TS 825', 'Şirket adı', TRUE),
('support_email', 'destek@ts825.com', 'Destek email adresi', TRUE);

-- Demo aktivite logları
INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES
(2, 'project_created', 'project', 1, '{"project_name": "Villa Projesi A"}', '192.168.1.100'),
(2, 'calculation_performed', 'calculation', 1, '{"calculation_type": "thermal_transmittance", "u_value": 0.385}', '192.168.1.100'),
(2, 'report_generated', 'report', 1, '{"report_type": "thermal_analysis"}', '192.168.1.100'),
(3, 'project_created', 'project', 3, '{"project_name": "Okul Binası C"}', '192.168.1.101'),
(4, 'login', 'user', 4, '{"login_time": "2024-05-28 10:30:00"}', '192.168.1.102');
