<?php
// migrate_v5.php — Corrige section_id a NULLABLE (las evaluaciones ya son por curso)
require_once __DIR__ . '/includes/config.php';
$pdo = db();

echo "=== Migración v5: section_id nullable ===\n";

// 1. Hacer section_id nullable
try {
    $pdo->exec("ALTER TABLE evaluations MODIFY section_id INT NULL");
    echo "✓ section_id ahora acepta NULL.\n";
} catch (PDOException $e) {
    echo "→ section_id: " . $e->getMessage() . "\n";
}

// 2. Recrear FK con ON DELETE SET NULL
try {
    // Ver si existe FK
    $fks = $pdo->query("
        SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evaluations'
        AND COLUMN_NAME = 'section_id' AND REFERENCED_TABLE_NAME = 'sections'
    ")->fetchAll();
    
    foreach ($fks as $fk) {
        $pdo->exec("ALTER TABLE evaluations DROP FOREIGN KEY {$fk['CONSTRAINT_NAME']}");
        echo "→ FK anterior eliminada: {$fk['CONSTRAINT_NAME']}\n";
    }
} catch (PDOException $e) {
    echo "→ FK cleanup: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE evaluations ADD CONSTRAINT fk_eval_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL");
    echo "✓ FK section_id → sections(id) ON DELETE SET NULL.\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate')) {
        echo "→ FK ya existe.\n";
    } else {
        echo "→ FK: " . $e->getMessage() . "\n";
    }
}

echo "\nMigración v5 completada.\n";
