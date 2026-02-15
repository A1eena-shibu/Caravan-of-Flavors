-- Caravan of Flavours Database Schema
-- Drop database if exists and create fresh
DROP DATABASE IF EXISTS caravan_db;
CREATE DATABASE caravan_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE caravan_db;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255), 
    full_name VARCHAR(255) NOT NULL,
    country VARCHAR(100),
    currency_code VARCHAR(10),
    currency_symbol VARCHAR(10),
    role ENUM('farmer', 'customer', 'admin', 'delivery_agent') NOT NULL DEFAULT 'customer',
    phone VARCHAR(20),
    address TEXT,
    google_id VARCHAR(255) UNIQUE, 
    profile_image VARCHAR(500),
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    admin_access ENUM('all', 'delivery_only') DEFAULT 'all',
    revenue_target DECIMAL(12, 6) DEFAULT 50000,
    low_stock_threshold INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- Products table (for farmers to add their spices)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    farmer_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(12, 6) NOT NULL,
    base_currency VARCHAR(10) DEFAULT 'INR',
    farmer_country VARCHAR(100),
    quantity DECIMAL(10, 2) NOT NULL,
    unit VARCHAR(20) DEFAULT 'kg',
    image_url VARCHAR(500),
    harvest_date DATE,
    expiry_date DATE,
    is_organic BOOLEAN DEFAULT FALSE,
    is_available BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    is_new_arrival BOOLEAN DEFAULT TRUE,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_farmer (farmer_id),
    INDEX idx_category (category)
) ENGINE=InnoDB;

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    farmer_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit_price DECIMAL(12, 6) NOT NULL,
    total_price DECIMAL(12, 6) NOT NULL,
    currency_code VARCHAR(10) DEFAULT 'INR',
    exchange_rate DECIMAL(10, 6) DEFAULT 1.000000,
    status ENUM('ordered', 'shipped', 'delivered', 'cancelled') DEFAULT 'ordered',
    delivery_address TEXT NOT NULL,
    delivery_agent_id INT NULL DEFAULT NULL,
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    notes TEXT,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_customer (customer_id),
    INDEX idx_farmer (farmer_id),
    INDEX idx_status (status),
    INDEX idx_order_date (order_date),
    INDEX idx_delivery_agent (delivery_agent_id)
) ENGINE=InnoDB;

-- Inventory table (for tracking stock)
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    farmer_id INT NOT NULL,
    current_stock DECIMAL(10, 2) NOT NULL,
    reserved_stock DECIMAL(10, 2) DEFAULT 0,
    available_stock DECIMAL(10, 2) GENERATED ALWAYS AS (current_stock - reserved_stock) STORED,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_farmer (product_id, farmer_id),
    INDEX idx_product (product_id),
    INDEX idx_farmer (farmer_id)
) ENGINE=InnoDB;


-- Admin activity logs
CREATE TABLE admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    target_table VARCHAR(50),
    target_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_action (action_type)
) ENGINE=InnoDB;


-- Order Tracking History
CREATE TABLE order_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    location VARCHAR(255),
    comment TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order (order_id)
) ENGINE=InnoDB;

-- Product Tracking History
CREATE TABLE product_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    quantity DECIMAL(10, 2),
    price DECIMAL(12, 6),
    unit VARCHAR(20),
    category VARCHAR(50),
    comment TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- Inventory Transaction Logs
CREATE TABLE inventory_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    change_amount DECIMAL(10, 2) NOT NULL,
    type ENUM('restock', 'sale', 'adjustment', 'return') NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- Insert default admin user
INSERT INTO users (email, password, full_name, country, currency_code, currency_symbol, role, is_verified, is_active, admin_access) 
VALUES ('admin@gmail.com', '$argon2id$v=19$m=65536,t=4,p=1$NE14V3o1WFpZWHhzM0hoeg$Hlu3RJendjqJbiZhdLXBvqM6E53Zd8VjMcC7JxKruLE', 'Super Admin', 'India', 'INR', '₹', 'admin', TRUE, TRUE, 'all');
-- Default password is: admin 

-- Default Farmer (Pass: 12345678)
INSERT INTO users (email, password, full_name, country, currency_code, currency_symbol, role, is_verified, is_active) 
VALUES ('farmer@gmail.com', '$2y$10$i/VpPfx5RKooEDszoBbbo.iuB6y3cMn.vJsr/przKLAYAn2gCs6.2', 'Joel Mathew', 'India', 'INR', '₹', 'farmer', TRUE, TRUE);

-- Default Customer (Pass: 12345678)
INSERT INTO users (email, password, full_name, country, currency_code, currency_symbol, role, is_verified, is_active) 
VALUES ('customer@gmail.com', '$2y$10$i/VpPfx5RKooEDszoBbbo.iuB6y3cMn.vJsr/przKLAYAn2gCs6.2', 'Aby Thomas', 'India', 'INR', '₹', 'customer', TRUE, TRUE);

-- Auctions table
CREATE TABLE auctions (
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
    shipping_status ENUM('pending', 'shipped', 'delivered') DEFAULT 'pending',
    shipping_address TEXT,
    phone VARCHAR(20),
    delivery_agent_id INT NULL DEFAULT NULL,
    tracking_number VARCHAR(100),
    shipped_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (delivery_agent_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_farmer (farmer_id),
    INDEX idx_status (status),
    INDEX idx_delivery_agent (delivery_agent_id)
) ENGINE=InnoDB;

-- Bids table
CREATE TABLE bids (
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

-- Exports table
CREATE TABLE exports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    farmer_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    destination_country VARCHAR(100) NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit VARCHAR(20) DEFAULT 'kg',
    status ENUM('pending', 'packaged', 'customs', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    tracking_number VARCHAR(100),
    shipping_carrier VARCHAR(100),
    shipment_date DATE,
    estimated_arrival DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_farmer (farmer_id),
    INDEX idx_status (status),
    INDEX idx_tracking (tracking_number)
) ENGINE=InnoDB;

-- Export Documents table
CREATE TABLE export_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    export_id INT NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    document_type ENUM('invoice', 'packing_list', 'phytosanitary', 'origin_certificate', 'bill_of_lading', 'other') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (export_id) REFERENCES exports(id) ON DELETE CASCADE,
    INDEX idx_export (export_id)
) ENGINE=InnoDB;

-- Cart table
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (customer_id, product_id),
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB;


-- Sample products for farmer@gmail.com (Farmer ID: 2)
INSERT INTO products (farmer_id, product_name, category, price, quantity, unit, image_url, is_featured, is_organic) VALUES
(2, 'Black Pepper', 'Whole Spices', 1500, 250.00, 'kg', 'uploads/products/black_pepper.webp', TRUE, TRUE),
(2, 'Turmeric Powder', 'Ground Spices', 550, 85.00, 'kg', 'uploads/products/turmeric_powder.jpg', TRUE, TRUE),
(2, 'Cardamom', 'Whole Spices', 2750, 15.00, 'kg', 'uploads/products/cardamom.jpg', FALSE, FALSE),
(2, 'Cinnamon Quills', 'Whole Spices', 1600, 500.00, 'kg', 'uploads/products/cinnamon.jpg', TRUE, FALSE),
(2, 'Star Anise', 'Whole Spices', 750, 0.00, 'kg', 'uploads/products/star_anise.jpg', FALSE, TRUE),
(2, 'Red Chili Flakes', 'Ground Spices', 230, 120.00, 'kg', 'uploads/products/red_chili.jpg', FALSE, FALSE),
(2, 'Ginger Powder', 'Ground Spices', 500, 45.00, 'kg', 'uploads/products/ginger.jpg', FALSE, TRUE),
(2, 'Clove Buds', 'Whole Spices', 1300, 200.00, 'kg', 'uploads/products/clove.jpg', TRUE, TRUE),
(2, 'Cumin Seeds', 'Whole Spices', 230, 10.00, 'kg', 'uploads/products/cumin.jpg', FALSE, FALSE),
(2, 'Saffron Threads', 'Luxury Spices', 300000, 0.50, 'kg', 'uploads/products/saffron.jpg', TRUE, TRUE);

-- Activity logs for hardcoded products
INSERT INTO product_tracking (product_id, action, quantity, price, unit, category, comment) VALUES
(1, 'listed', 250.00, 1500, 'kg', 'Whole Spices', 'Initial stock listing'),
(2, 'listed', 85.00, 550, 'kg', 'Ground Spices', 'Initial stock listing'),
(3, 'listed', 15.00, 2750, 'kg', 'Whole Spices', 'Initial stock listing'),
(4, 'listed', 500.00, 1600, 'kg', 'Whole Spices', 'Initial stock listing'),
(5, 'listed', 0.00, 750, 'kg', 'Whole Spices', 'Initial stock listing'),
(6, 'listed', 120.00, 230, 'kg', 'Ground Spices', 'Initial stock listing'),
(7, 'listed', 45.00, 500, 'kg', 'Ground Spices', 'Initial stock listing'),
(8, 'listed', 200.00, 1300, 'kg', 'Whole Spices', 'Initial stock listing'),
(9, 'listed', 10.00, 230, 'kg', 'Whole Spices', 'Initial stock listing'),
(10, 'listed', 0.50, 300000, 'kg', 'Luxury Spices', 'Initial stock listing');

-- Reviews table
CREATE TABLE reviews (
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
CREATE TABLE notifications (
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
