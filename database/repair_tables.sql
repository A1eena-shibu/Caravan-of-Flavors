-- ============================================================
-- Caravan of Flavours — Table Repair Script
-- Fixes InnoDB "doesn't exist in engine" errors
-- Safe to run multiple times (uses IF NOT EXISTS)
-- ============================================================
USE caravan_db;

-- ── Step 1: Drop corrupted tables in reverse dependency order ──
-- (Only those known to be broken — auctions and its dependencies)

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS export_tracking;
DROP TABLE IF EXISTS export_documents;
DROP TABLE IF EXISTS export_requests;
DROP TABLE IF EXISTS bids;
DROP TABLE IF EXISTS auctions;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS order_tracking;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Step 2: Recreate all dropped tables ──────────────────────

-- Auctions table
CREATE TABLE IF NOT EXISTS auctions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    farmer_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    starting_price DECIMAL(10, 2) NOT NULL,
    base_currency VARCHAR(10) DEFAULT 'INR',
    farmer_country VARCHAR(100),
    current_bid DECIMAL(10, 2) DEFAULT 0.00,
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
    unit VARCHAR(20) DEFAULT 'kg',
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    image_url VARCHAR(500),
    status ENUM('scheduled', 'active', 'completed', 'cancelled') DEFAULT 'scheduled',
    winner_id INT,
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    shipping_status ENUM('pending', 'shipped', 'delivered', 'shipped_pending') DEFAULT 'pending',
    shipping_address TEXT,
    phone VARCHAR(20),
    delivery_agent_id INT NULL DEFAULT NULL,
    delivery_staff_id INT NULL DEFAULT NULL,
    tracking_number VARCHAR(100),
    delivery_otp VARCHAR(4) DEFAULT NULL,
    delivery_otp_sent_at TIMESTAMP NULL DEFAULT NULL,
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (delivery_agent_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (delivery_staff_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_farmer (farmer_id),
    INDEX idx_status (status),
    INDEX idx_delivery_agent (delivery_agent_id),
    INDEX idx_delivery_staff (delivery_staff_id)
) ENGINE=InnoDB;

-- Bids table
CREATE TABLE IF NOT EXISTS bids (
    id INT PRIMARY KEY AUTO_INCREMENT,
    auction_id INT NOT NULL,
    customer_id INT NOT NULL,
    bid_amount DECIMAL(10, 2) NOT NULL,
    bid_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_auction (auction_id),
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB;

-- Order Tracking History
CREATE TABLE IF NOT EXISTS order_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    type ENUM('order', 'auction') DEFAULT 'order',
    location VARCHAR(255),
    comment TEXT,
    created_by INT NULL DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_type (order_id, type),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB;

-- Reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    customer_id INT NOT NULL,
    order_id INT NOT NULL,
    rating INT NOT NULL DEFAULT 5,
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (customer_id, order_id)
) ENGINE=InnoDB;

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Export Requests table
CREATE TABLE IF NOT EXISTS export_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    farmer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit VARCHAR(20) DEFAULT 'kg',
    target_country VARCHAR(100) NOT NULL,
    shipping_port VARCHAR(150),
    preferred_shipping_mode ENUM('sea', 'air') DEFAULT 'sea',
    business_name VARCHAR(255),
    business_registration_no VARCHAR(100),
    importer_license_no VARCHAR(100),
    contact_person VARCHAR(150) DEFAULT NULL,
    contact_email VARCHAR(200) DEFAULT NULL,
    contact_phone VARCHAR(50) DEFAULT NULL,
    delivery_street VARCHAR(255) DEFAULT NULL,
    delivery_city VARCHAR(100) DEFAULT NULL,
    delivery_postal_code VARCHAR(20) DEFAULT NULL,
    incoterms VARCHAR(10) DEFAULT NULL,
    order_type ENUM('bulk','sample') DEFAULT 'bulk',
    required_delivery_date DATE DEFAULT NULL,
    offered_price DECIMAL(12, 6),
    currency_code VARCHAR(10) DEFAULT 'INR',
    exchange_rate DECIMAL(10, 6) DEFAULT 1.000000,
    payment_terms ENUM('advance', 'lc', 'dp', 'da') DEFAULT 'advance',
    requires_organic_cert BOOLEAN DEFAULT FALSE,
    requires_phytosanitary BOOLEAN DEFAULT FALSE,
    requires_quality_test BOOLEAN DEFAULT FALSE,
    packaging_requirements TEXT,
    special_notes TEXT,
    farmer_notes TEXT,
    admin_notes TEXT,
    admin_reviewed_by INT NULL,
    admin_reviewed_at TIMESTAMP NULL,
    status ENUM('pending','under_review','approved','rejected','quality_testing','documentation','shipped','delivered','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_farmer (farmer_id),
    INDEX idx_product (product_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Export Documents table
CREATE TABLE IF NOT EXISTS export_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    export_request_id INT NOT NULL,
    document_type ENUM('commercial_invoice','packing_list','bill_of_lading','certificate_of_origin','phytosanitary_certificate','quality_certificate','insurance_certificate','iec_certificate','spices_board_cert','fssai_license','other') NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (export_request_id) REFERENCES export_requests(id) ON DELETE CASCADE,
    INDEX idx_export_request (export_request_id)
) ENGINE=InnoDB;

-- Export Tracking table
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

-- ── Done ─────────────────────────────────────────────────────
SELECT 'Repair complete! All tables recreated successfully.' AS Status;

-- ── Returns & Wallet tables (add if missing) ─────────────────
CREATE TABLE IF NOT EXISTS `returns` (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    order_id    INT NOT NULL,
    customer_id INT NOT NULL,
    farmer_id   INT NOT NULL,
    product_id  INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    reason      ENUM('damaged','wrong_item','expired','quality_issue','other') NOT NULL,
    description TEXT,
    image_path  VARCHAR(500) DEFAULT NULL,
    refund_method  ENUM('wallet','original') DEFAULT 'wallet',
    refund_amount  DECIMAL(12,6) DEFAULT 0,
    currency_code  VARCHAR(10)  DEFAULT 'INR',
    status ENUM('requested','under_review','approved','rejected','refund_processing','refund_completed') DEFAULT 'requested',
    admin_notes VARCHAR(1000) DEFAULT NULL,
    reviewed_by INT           DEFAULT NULL,
    reviewed_at TIMESTAMP     NULL DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)    REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (farmer_id)   REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_order    (order_id),
    INDEX idx_customer (customer_id),
    INDEX idx_status   (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wallet (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    user_id    INT NOT NULL UNIQUE,
    balance    DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    user_id        INT NOT NULL,
    amount         DECIMAL(12,2) NOT NULL,
    type           ENUM('credit','debit') NOT NULL,
    description    VARCHAR(500) DEFAULT NULL,
    reference_id   INT          DEFAULT NULL,
    reference_type VARCHAR(50)  DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user    (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

SELECT 'Returns & Wallet tables ensured!' AS Status;

