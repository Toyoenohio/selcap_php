<?php
// ═══════════════════════════════════════════════
// Migrate v10 — Tabla course_surveys (encuesta post-examen)
// ═══════════════════════════════════════════════
require_once __DIR__ . '/includes/config.php';

$pdo = db();

$sql = "
CREATE TABLE IF NOT EXISTS course_surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    eval_general TINYINT DEFAULT NULL COMMENT 'Q1: satisfacción general (1-4)',
    eval_contenido TINYINT DEFAULT NULL COMMENT 'Q2: claridad/utilidad del contenido (1-4)',
    eval_instructor TINYINT DEFAULT NULL COMMENT 'Q3: desempeño del instructor (1-4)',
    recomendaria TINYINT DEFAULT NULL COMMENT 'Q4: ¿recomendaría? (1=SÍ, 0=NO)',
    comentarios TEXT DEFAULT NULL COMMENT 'Q5: comentarios abiertos',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_user_course (user_id, course_id),
    KEY idx_course (course_id),
    KEY idx_created (created_at),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($sql);
    echo "✅ Tabla 'course_surveys' creada correctamente.\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
