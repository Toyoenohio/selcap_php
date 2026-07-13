<?php
// migrate_v7.php — Agrega active_from y active_until para programar evaluaciones
require_once __DIR__ . '/includes/config.php';
$pdo = db();

echo "=== Migración v7: active_from / active_until en evaluations ===\n";

try {
    $pdo->exec("ALTER TABLE evaluations ADD COLUMN active_from DATETIME NULL");
    echo "✓ Columna active_from agregada.\n";
} catch (PDOException $e) {
    echo "→ active_from: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE evaluations ADD COLUMN active_until DATETIME NULL");
    echo "✓ Columna active_until agregada.\n";
} catch (PDOException $e) {
    echo "→ active_until: " . $e->getMessage() . "\n";
}

echo "\nMigración v7 completada.\n";
