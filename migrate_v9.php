<?php
// migrate_v9.php — Campos por evaluación para certificado: horas + fecha del curso
require_once __DIR__ . '/includes/config.php';
$pdo = db();

echo "=== Migración v9: horas y fecha en evaluations ===\n";

// Horas del curso (por evaluación, con fallback al curso)
try {
    $pdo->exec("ALTER TABLE evaluations ADD COLUMN hours INT NULL");
    echo "✓ evaluations.hours agregado.\n";
} catch (PDOException $e) {
    echo "→ evaluations.hours: " . $e->getMessage() . "\n";
}

// Rango de fechas del curso (texto libre: "Del 05 y 08 de Mayo, 2025")
try {
    $pdo->exec("ALTER TABLE evaluations ADD COLUMN date_range VARCHAR(255) NULL");
    echo "✓ evaluations.date_range agregado.\n";
} catch (PDOException $e) {
    echo "→ evaluations.date_range: " . $e->getMessage() . "\n";
}

echo "\nMigración v9 completada.\n";
