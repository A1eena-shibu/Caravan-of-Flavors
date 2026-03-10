-- ============================================================
-- Caravan of Flavours — Returns & Wallet Schema
-- Run this once after the main schema.sql
-- Safe to run multiple times (uses CREATE TABLE IF NOT EXISTS)
-- ============================================================
USE caravan_db;

-- ── Returns table ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `returns` (
    id INT PRIMARY KEY AUTO_INCREMENT,

    -- References
    order_id    INT NOT NULL,
    customer_id INT NOT NULL,
    farmer_id   INT NOT NULL,
    product_id  INT NOT NULL,

    -- Product snapshot (denormalised so it survives product deletion)
    product_name VARCHAR(255) NOT NULL,

    -- Return details
    reason      ENUM('damaged','wrong_item','expired','quality_issue','other') NOT NULL,
    description TEXT,
    image_path  VARCHAR(500) DEFAULT NULL,       -- proof photo uploaded by customer

    -- Refund info
    refund_method  ENUM('wallet','original') DEFAULT 'wallet',
    refund_amount  DECIMAL(12,6) DEFAULT 0,
    currency_code  VARCHAR(10)  DEFAULT 'INR',

    -- Status flow:
    -- requested → under_review → approved / rejected → refund_processing → refund_completed
    status ENUM(
        'requested',
        'under_review',
        'approved',
        'rejected',
        'refund_processing',
        'refund_completed'
    ) DEFAULT 'requested',

    -- Admin fields
    admin_notes VARCHAR(1000) DEFAULT NULL,
    reviewed_by INT           DEFAULT NULL,
    reviewed_at TIMESTAMP     NULL DEFAULT NULL,

    -- Timestamps
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Constraints
    FOREIGN KEY (order_id)    REFERENCES orders(id)  ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (farmer_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id)   ON DELETE SET NULL,

    INDEX idx_order    (order_id),
    INDEX idx_customer (customer_id),
    INDEX idx_status   (status),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB;

-- ── Wallet table ─────────────────────────────────────────────
-- One row per customer — balance is cumulative
CREATE TABLE IF NOT EXISTS wallet (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    user_id    INT NOT NULL UNIQUE,
    balance    DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ── Wallet Transactions table ─────────────────────────────────
-- Audit trail for every credit / debit
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    user_id        INT NOT NULL,
    amount         DECIMAL(12,2) NOT NULL,
    type           ENUM('credit','debit') NOT NULL,
    description    VARCHAR(500) DEFAULT NULL,
    reference_id   INT          DEFAULT NULL,   -- return_id or order_id
    reference_type VARCHAR(50)  DEFAULT NULL,   -- 'return' | 'order' | 'manual'
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user    (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ── Done ─────────────────────────────────────────────────────
SELECT 'Returns & Wallet tables created successfully!' AS Status;
