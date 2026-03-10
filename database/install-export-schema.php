<?php
/**
 * Export Schema Installer
 * Run this once via browser: http://localhost/caravan/database/install-export-schema.php
 * DELETE THIS FILE after running!
 */

require_once '../backend/config/database.php';

$pdo = getDBConnection();

$sql = "
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
    offered_price DECIMAL(12, 6),
    currency_code VARCHAR(10) DEFAULT 'INR',
    exchange_rate DECIMAL(10, 6) DEFAULT 1.000000,
    payment_terms ENUM('advance', 'lc', 'dp', 'da') DEFAULT 'advance',
    requires_organic_cert BOOLEAN DEFAULT FALSE,
    requires_phytosanitary BOOLEAN DEFAULT FALSE,
    requires_quality_test BOOLEAN DEFAULT FALSE,
    packaging_requirements TEXT,
    special_notes TEXT,
    status ENUM('pending','under_review','approved','rejected','quality_testing','documentation','shipped','delivered','cancelled') DEFAULT 'pending',
    farmer_notes TEXT,
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
    INDEX idx_status (status)
) ENGINE=InnoDB;
";

$sql2 = "
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
";

$sql3 = "
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
";

$errors = [];
$success = [];

try {
    $pdo->exec($sql);
    $success[] = '✅ export_requests table created successfully';
} catch (PDOException $e) {
    $errors[] = '❌ export_requests: ' . $e->getMessage();
}

try {
    $pdo->exec($sql2);
    $success[] = '✅ export_tracking table created successfully';
} catch (PDOException $e) {
    $errors[] = '❌ export_tracking: ' . $e->getMessage();
}

try {
    $pdo->exec($sql3);
    $success[] = '✅ export_documents table created successfully';
} catch (PDOException $e) {
    $errors[] = '❌ export_documents: ' . $e->getMessage();
}

$sql4 = "
CREATE TABLE IF NOT EXISTS farmer_compliance_docs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    farmer_id INT NOT NULL,
    document_type ENUM(
        'iec_certificate','spices_board_cert','fssai_license',
        'quality_certificate','phytosanitary_certificate',
        'commercial_invoice','packing_list','bill_of_lading',
        'certificate_of_origin','insurance_certificate','other'
    ) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_farmer_doc_type (farmer_id, document_type),
    INDEX idx_farmer (farmer_id)
) ENGINE=InnoDB;
";

try {
    $pdo->exec($sql4);
    $success[] = '✅ farmer_compliance_docs table created successfully';
} catch (PDOException $e) {
    $errors[] = '❌ farmer_compliance_docs: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Export Schema Installer</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 700px; margin: 60px auto; padding: 0 20px; }
        h1 { color: #FF7E21; }
        .success { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 16px; color: #166534; margin-bottom: 8px; font-weight: 600; }
        .error { background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 12px 16px; color: #991b1b; margin-bottom: 8px; font-weight: 600; }
        .warning { background: #fef9c3; border: 1px solid #fde68a; padding: 16px; border-radius: 10px; margin-top: 20px; color: #a16207; font-weight: 700; }
    </style>
</head>
<body>
    <h1>🌍 Export Schema Installer</h1>
    <p style="color: #78716c;">This installs the database tables required for the Caravan of Flavours Export Feature.</p>
    <hr style="margin: 20px 0; border-color: #f5f5f4;">
    <?php foreach ($success as $msg): ?>
        <div class="success"><?= $msg ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $msg): ?>
        <div class="error"><?= $msg ?></div>
    <?php endforeach; ?>
    <?php if (empty($errors)): ?>
        <div class="warning">
            ⚠️ Installation complete! <strong>Please delete this file immediately</strong> for security:<br>
            <code style="font-size: 13px;">database/install-export-schema.php</code>
        </div>
    <?php endif; ?>
</body>
</html>
