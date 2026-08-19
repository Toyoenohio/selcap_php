<?php
// ═══════════════════════════════════════════════
// Migrate v11 — Tabla course_surveys corregida (11 preguntas reales del PDF)
// Recrea la tabla porque v10 usó preguntas inventadas y estaba vacía.
// ═══════════════════════════════════════════════
require_once __DIR__ . '/includes/config.php';

$pdo = db();

// Si existe, respaldar datos (por si acaso) y recrear
try {
    $rowCount = (int)$pdo->query('SELECT COUNT(*) FROM course_surveys')->fetchColumn();
    if ($rowCount > 0) {
        $backupTable = 'course_surveys_backup_' . date('Ymd_His');
        $pdo->exec("CREATE TABLE $backupTable LIKE course_surveys");
        $pdo->exec("INSERT INTO $backupTable SELECT * FROM course_surveys");
        echo "ℹ️  Había $rowCount fila(s) — respaldadas en '$backupTable'.\n";
    }
} catch (PDOException $e) {
    // tabla no existe aún — ok
}

$pdo->exec('DROP TABLE IF EXISTS course_surveys');

$sql = "
CREATE TABLE course_surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,

    -- Escalas 1-4 (Obligatorias)
    eval_general           TINYINT DEFAULT NULL COMMENT 'Q1: Evalúa de forma general el curso (1-4)',
    eval_tecnologia        TINYINT DEFAULT NULL COMMENT 'Q3: Evalúe la tecnología usada (1-4)',
    horario_adecuado       TINYINT DEFAULT NULL COMMENT 'Q5: ¿Le acomoda este horario para talleres online? (1-4)',
    proceso_inscripcion    TINYINT DEFAULT NULL COMMENT 'Q6: Evalúe el proceso de inscripción al curso (1-4)',
    efectividad_relator    TINYINT DEFAULT NULL COMMENT 'Q7: Evalúe la efectividad global del relator (1-4)',

    -- Textos (Optativos)
    mejoras                TEXT DEFAULT NULL COMMENT 'Q2: Aspectos para posibles mejoras',
    dificultades_tecnologia TEXT DEFAULT NULL COMMENT 'Q4: Dificultades en el uso de la tecnología',
    comentario_final       TEXT DEFAULT NULL COMMENT 'Q8: Comentario final',
    experiencia            TEXT DEFAULT NULL COMMENT 'Q9: Compártenos tu experiencia del curso',

    -- Publicación (Optativos)
    autoriza_publicar      TINYINT DEFAULT NULL COMMENT 'Q10: ¿Autorizas compartir tu opinión públicamente? (1=Sí, 0=No)',
    nombre_publico         VARCHAR(150) DEFAULT NULL COMMENT 'Q11: Nombre y apellido (si autorizó)',

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
    echo "✅ Tabla 'course_surveys' recreada con el esquema real (11 preguntas).\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
