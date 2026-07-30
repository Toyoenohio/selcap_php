<?php
// ═══════════════════════════════════════════════
// Migrate v9 — Tabla certificates para PDFs generados vía n8n
// ═══════════════════════════════════════════════
require_once __DIR__ . '/includes/config.php';

$pdo = db();

$sql = "
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    folio VARCHAR(20) NOT NULL,
    certificate_url VARCHAR(500) DEFAULT NULL,
    status ENUM('pending','processing','ready','error') DEFAULT 'pending',
    n8n_execution_id VARCHAR(100) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    generated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_attempt (attempt_id),
    KEY idx_user (user_id),
    KEY idx_status (status),

    FOREIGN KEY (attempt_id) REFERENCES evaluation_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($sql);
    echo "✅ Tabla 'certificates' creada correctamente.\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
