<?php
// migrate_v4.php — Agrega course_id a evaluations y lo puebla desde section_id
require_once __DIR__ . '/includes/db.php';
$pdo = db();

echo "=== Migración v4: Evaluaciones por curso ===\n";

// 1. Agregar columna course_id
try {
    $pdo->exec("ALTER TABLE evaluations ADD COLUMN course_id INT NULL AFTER section_id");
    echo "✓ Columna course_id agregada.\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        echo "→ course_id ya existe.\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

// 2. Poblar course_id desde section_id
$updated = $pdo->exec("UPDATE evaluations e JOIN sections s ON e.section_id = s.id SET e.course_id = s.course_id WHERE e.course_id IS NULL");
echo "✓ $updated evaluaciones actualizadas con course_id.\n";

// 3. Hacer course_id NOT NULL (opcional, lo dejamos nullable por ahora para compatibilidad)
// $pdo->exec("ALTER TABLE evaluations MODIFY course_id INT NOT NULL");

// 4. Agregar foreign key
try {
    $pdo->exec("ALTER TABLE evaluations ADD FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE");
    echo "✓ Foreign key course_id agregada.\n";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key') || str_contains($e->getMessage(), 'Duplicate key')) {
        echo "→ Foreign key ya existe.\n";
    } else {
        echo "→ FK: " . $e->getMessage() . "\n";
    }
}

echo "\nMigración v4 completada.\n";
