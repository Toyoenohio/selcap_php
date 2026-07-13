<?php
// migrate_v6.php — Agrega columna is_active a evaluations (switch on/off)
require_once __DIR__ . '/includes/config.php';
$pdo = db();

echo "=== Migración v6: is_active en evaluations ===\n";

try {
    $pdo->exec("ALTER TABLE evaluations ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0");
    echo "✓ Columna is_active agregada.\n";
} catch (PDOException $e) {
    echo "→ " . $e->getMessage() . "\n";
}

echo "\nMigración v6 completada.\n";
