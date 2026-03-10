-- ============================================================
-- Export Feature Schema Update
-- Caravan of Flavours - Spice Export Management
-- Run this after the main schema.sql
-- ============================================================
USE caravan_db;

-- Export Requests table
-- Customer (international buyer) requests to export spice directly from farmer
CREATE TABLE IF NOT EXISTS export_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    farmer_id INT NOT NULL,
    product_id INT NOT NULL,

    -- Export Specifics
    quantity DECIMAL(10, 2) NOT NULL,
    unit VARCHAR(20) DEFAULT 'kg',
    target_country VARCHAR(100) NOT NULL,
    shipping_port VARCHAR(150),
    preferred_shipping_mode ENUM('sea', 'air') DEFAULT 'sea',

    -- Business & Compliance Info
    business_name VARCHAR(255),
    business_registration_no VARCHAR(100),
    importer_license_no VARCHAR(100),

    -- Pricing & Payment
    offered_price DECIMAL(12, 6),
    currency_code VARCHAR(10) DEFAULT 'INR',
    exchange_rate DECIMAL(10, 6) DEFAULT 1.000000,
    payment_terms ENUM('advance', 'lc', 'dp', 'da') DEFAULT 'advance',

    -- Quality & Certifications needed
    requires_organic_cert BOOLEAN DEFAULT FALSE,
    requires_phytosanitary BOOLEAN DEFAULT FALSE,
    requires_quality_test BOOLEAN DEFAULT FALSE,

    -- Special requirements
    packaging_requirements TEXT,
    special_notes TEXT,

    -- Status tracking
    status ENUM(
        'pending',
        'under_review',
        'approved',
        'rejected',
        'quality_testing',
        'documentation',
        'shipped',
        'delivered',
        'cancelled'
    ) DEFAULT 'pending',

    -- Rejection / Note from farmer
    farmer_notes TEXT,

    -- Admin review
    admin_notes TEXT,
    admin_reviewed_by INT NULL,
    admin_reviewed_at TIMESTAMP NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_reviewed_by) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_customer (customer_id),
    INDEX idx_farmer (farmer_id),
    INDEX idx_product (product_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Export Documents table (for tracking required export documentation)
CREATE TABLE IF NOT EXISTS export_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    export_request_id INT NOT NULL,
    document_type ENUM(
        'commercial_invoice',
        'packing_list',
        'bill_of_lading',
        'certificate_of_origin',
        'phytosanitary_certificate',
        'quality_certificate',
        'insurance_certificate',
        'iec_certificate',
        'spices_board_cert',
        'fssai_license',
        'other'
    ) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (export_request_id) REFERENCES export_requests(id) ON DELETE CASCADE,
    INDEX idx_export_request (export_request_id)
) ENGINE=InnoDB;

-- Export status tracking history
CREATE TABLE IF NOT EXISTS export_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    export_request_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (export_request_id) REFERENCES export_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_export_request (export_request_id)
) ENGINE=InnoDB;
