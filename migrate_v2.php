<?php
/**
 * Migración v2 — Ejecutar UNA SOLA VEZ desde el navegador
 * https://aula.selcap.cl/migrate_v2.php
 */

require_once __DIR__ . '/includes/config.php';

$pdo = db();
$results = [];

function safeExec($pdo, $sql, $label) {
    global $results;
    try {
        $pdo->exec($sql);
        $results[] = "✅ $label";
        return true;
    } catch (PDOException $e) {
        if (in_array($e->getCode(), ['42S21', '42S01', '42S22'])) {
            $results[] = "⏭️ $label (ya existe)";
            return true;
        }
        $results[] = "❌ $label: " . $e->getMessage();
        return false;
    }
}

safeExec($pdo, "ALTER TABLE evaluations ADD COLUMN passing_score INT DEFAULT 80 AFTER max_attempts", 'passing_score en evaluations');
safeExec($pdo, "ALTER TABLE evaluations MODIFY max_attempts INT DEFAULT 1", 'max_attempts = 1');
safeExec($pdo, "UPDATE evaluations SET passing_score = 80 WHERE passing_score IS NULL", 'passing_score defaults');
safeExec($pdo, "ALTER TABLE evaluation_attempts ADD COLUMN feedback TEXT AFTER answers_snapshot", 'feedback en evaluation_attempts');
safeExec($pdo, "CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'tabla audit_log');

safeExec($pdo, "ALTER TABLE courses ADD COLUMN sku VARCHAR(100) UNIQUE AFTER thumbnail_url", 'sku en courses');
safeExec($pdo, "UPDATE courses SET sku = 'FUND-SEG-IND' WHERE id = 1 AND sku IS NULL", 'sku curso demo');

safeExec($pdo, "ALTER TABLE lessons ADD COLUMN live_url VARCHAR(500) AFTER video_url", 'live_url en lessons');

safeExec($pdo, "CREATE TABLE IF NOT EXISTS course_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'tabla course_materials');

safeExec($pdo, "ALTER TABLE sections ADD COLUMN live_url VARCHAR(500) AFTER description", 'live_url en sections');

$allOk = !in_array(false, array_map(fn($r) => !str_starts_with($r, '❌'), $results), true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración v2 — Selcap AV</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 max-w-md w-full">
        <h1 class="text-xl font-extrabold text-gray-900 mb-4">Migración v2</h1>
        <div class="space-y-2 mb-6">
            <?php foreach ($results as $r): ?>
                <p class="text-sm font-mono <?= str_starts_with($r, '✅') ? 'text-green-700' : (str_starts_with($r, '⏭️') ? 'text-gray-500' : 'text-red-600') ?>"><?= $r ?></p>
            <?php endforeach; ?>
        </div>
        <?php if ($allOk): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm mb-4">
                Migración completada. Ya puedes eliminar este archivo.
            </div>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/dashboard.php" class="block text-center bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">
            Ir al panel
        </a>
    </div>
</body>
</html>
