-- BONUS TS 825 Hesap Programı
-- Tam Veritabanı Şeması

-- Veritabanı oluştur
CREATE DATABASE IF NOT EXISTS ts825_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ts825_db;

-- Kullanıcılar tablosu
CREATE TABLE users (
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
);

-- Kullanıcı oturumları tablosu
CREATE TABLE user_sessions (
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
);

-- Projeler tablosu
CREATE TABLE projects (
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
);

-- Malzemeler tablosu
CREATE TABLE materials (
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
);

-- Yapı elemanları tablosu
CREATE TABLE building_elements (
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
);

-- Yapı elemanı katmanları tablosu
CREATE TABLE element_layers (
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
);

-- Hesaplamalar tablosu
CREATE TABLE calculations (
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
);

-- Isı köprüleri tablosu
CREATE TABLE thermal_bridges (
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
);

-- Raporlar tablosu
CREATE TABLE reports (
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
);

-- Sistem ayarları tablosu
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- Aktivite logları tablosu
CREATE TABLE activity_logs (
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
);

-- Dosya yüklemeleri tablosu
CREATE TABLE file_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT,
    original_name VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    file_type ENUM('image', 'document', 'calculation', 'report', 'other') DEFAULT 'other',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_project_id (project_id),
    INDEX idx_file_type (file_type)
);
