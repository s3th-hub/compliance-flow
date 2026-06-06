-- Business Compliance Management Platform
-- Database Schema v1.0

CREATE DATABASE IF NOT EXISTS business_compliance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE business_compliance;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','officer') NOT NULL DEFAULT 'officer',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table: businesses
-- --------------------------------------------------------
CREATE TABLE businesses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(180) NOT NULL,
    registration_number VARCHAR(80) NOT NULL UNIQUE,
    industry_type VARCHAR(100) NOT NULL,
    physical_address TEXT NOT NULL,
    contact_person VARCHAR(120) NOT NULL,
    contact_email VARCHAR(180),
    contact_phone VARCHAR(30),
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table: compliance_categories
-- --------------------------------------------------------
CREATE TABLE compliance_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table: compliance_records
-- --------------------------------------------------------
CREATE TABLE compliance_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    issue_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('active','expired','pending_renewal') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES compliance_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table: documents
-- --------------------------------------------------------
CREATE TABLE documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    compliance_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(80),
    file_size INT UNSIGNED,
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (compliance_id) REFERENCES compliance_records(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Seed Data
-- --------------------------------------------------------

-- Default admin user (password: Admin@1234)
INSERT INTO users (full_name, email, password_hash, role) VALUES
('System Administrator', 'admin@compliance.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Jane Compliance', 'officer@compliance.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer');

-- Default categories
INSERT INTO compliance_categories (name, description) VALUES
('Business Permit', 'Annual operating permits issued by local authorities'),
('Tax Compliance', 'KRA tax compliance certificates and returns'),
('Fire Safety', 'Fire safety inspection certificates'),
('Company Registration', 'Registrar of Companies incorporation documents'),
('Health & Safety', 'Occupational health and safety certificates'),
('Environmental', 'NEMA environmental compliance certificates'),
('Labour', 'NSSF, NHIF, and labour compliance records');

-- Sample business
INSERT INTO businesses (business_name, registration_number, industry_type, physical_address, contact_person, contact_email, contact_phone, created_by) VALUES
('Acme Trading Ltd', 'BN/2020/001234', 'Retail', 'Kimathi Street, Nairobi CBD', 'John Doe', 'john@acme.co.ke', '+254700000001', 1),
('BuildRight Construction', 'BN/2019/005678', 'Construction', 'Industrial Area, Nairobi', 'Mary Wanjiru', 'mary@buildright.co.ke', '+254700000002', 1);
