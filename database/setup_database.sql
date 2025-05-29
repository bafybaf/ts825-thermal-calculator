-- TS825 Veritabanı Kurulum Scripti
-- Veritabanı: bonusyalitim_ts825

-- Kullanıcılar tablosu oluştur
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `full_name` varchar(100) DEFAULT NULL,
    `email` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `role` enum('user','admin') DEFAULT 'user',
    `status` enum('active','inactive') DEFAULT 'active',
    `phone` varchar(20) DEFAULT NULL,
    `company` varchar(100) DEFAULT NULL,
    `title` varchar(100) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    KEY `idx_role` (`role`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Test kullanıcıları ekle
INSERT INTO `users` (`username`, `full_name`, `email`, `password`, `role`, `status`, `phone`, `company`, `title`) VALUES
('admin', 'Sistem Yöneticisi', 'admin@bonusyalitim.com.tr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', '+90 555 123 4567', 'Bonus Yalıtım', 'Sistem Yöneticisi'),
('engineer1', 'Ahmet Yılmaz', 'ahmet@bonusyalitim.com.tr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'active', '+90 555 234 5678', 'Bonus Yalıtım', 'Makine Mühendisi'),
('engineer2', 'Fatma Demir', 'fatma@bonusyalitim.com.tr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'active', '+90 555 345 6789', 'Bonus Yalıtım', 'İnşaat Mühendisi')
ON DUPLICATE KEY UPDATE 
    `full_name` = VALUES(`full_name`),
    `phone` = VALUES(`phone`),
    `company` = VALUES(`company`),
    `title` = VALUES(`title`);

-- Projeler tablosu oluştur
CREATE TABLE IF NOT EXISTS `projects` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `project_code` varchar(50) DEFAULT NULL,
    `building_type` varchar(50) DEFAULT NULL,
    `climate_zone` int(1) DEFAULT NULL,
    `total_area` decimal(10,2) DEFAULT NULL,
    `status` enum('draft','in_progress','completed') DEFAULT 'draft',
    `description` text DEFAULT NULL,
    `user_id` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_building_type` (`building_type`),
    CONSTRAINT `fk_projects_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Test projeleri ekle
INSERT INTO `projects` (`name`, `project_code`, `building_type`, `climate_zone`, `total_area`, `status`, `description`, `user_id`) VALUES
('Konut Projesi A', 'KNT-2024-001', 'residential', 3, 150.00, 'completed', 'Müstakil konut projesi. TS 825 standartlarına uygun tasarım.', 1),
('Ofis Binası B', 'OFS-2024-002', 'commercial', 4, 500.00, 'in_progress', 'Çok katlı ofis binası projesi. LEED sertifikası hedeflenmektedir.', 2),
('Okul Binası C', 'EGT-2024-003', 'educational', 4, 800.00, 'draft', 'İlkokul binası projesi. Enerji verimli tasarım hedeflenmektedir.', 3)
ON DUPLICATE KEY UPDATE 
    `name` = VALUES(`name`),
    `description` = VALUES(`description`);

-- Hesaplamalar tablosu oluştur
CREATE TABLE IF NOT EXISTS `calculations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `project_id` int(11) NOT NULL,
    `calculation_type` varchar(50) NOT NULL,
    `element_type` varchar(50) DEFAULT NULL,
    `area` decimal(10,2) DEFAULT NULL,
    `u_value` decimal(8,4) DEFAULT NULL,
    `is_compliant` tinyint(1) DEFAULT NULL,
    `calculation_data` json DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_calculation_type` (`calculation_type`),
    CONSTRAINT `fk_calculations_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raporlar tablosu oluştur
CREATE TABLE IF NOT EXISTS `reports` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `project_id` int(11) NOT NULL,
    `report_type` varchar(50) NOT NULL,
    `file_name` varchar(255) DEFAULT NULL,
    `file_path` varchar(500) DEFAULT NULL,
    `file_size` int(11) DEFAULT NULL,
    `generated_by` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_generated_by` (`generated_by`),
    CONSTRAINT `fk_reports_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reports_user` FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Veritabanı optimizasyonu
OPTIMIZE TABLE `users`;
OPTIMIZE TABLE `projects`;
OPTIMIZE TABLE `calculations`;
OPTIMIZE TABLE `reports`;

-- Kontrol sorguları
SELECT 'Veritabanı tabloları başarıyla oluşturuldu!' as message;
SELECT COUNT(*) as user_count FROM users;
SELECT COUNT(*) as project_count FROM projects;

-- Test şifresi: password (hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi)
