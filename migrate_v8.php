<?php
// migrate_v8.php — Campos para certificado: RUT alumno + datos del curso
require_once __DIR__ . '/includes/config.php';
$pdo = db();

echo "=== Migración v8: campos para certificado ===\n";

// RUT en users
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN rut VARCHAR(20) NULL");
    echo "✓ users.rut agregado.\n";
} catch (PDOException $e) {
    echo "→ users.rut: " . $e->getMessage() . "\n";
}

// Horas del curso
try {
    $pdo->exec("ALTER TABLE courses ADD COLUMN hours INT NULL");
    echo "✓ courses.hours agregado.\n";
} catch (PDOException $e) {
    echo "→ courses.hours: " . $e->getMessage() . "\n";
}

// Rango de fechas del curso (texto libre: "Del 05 y 08 de Mayo, 2025")
try {
    $pdo->exec("ALTER TABLE courses ADD COLUMN date_range VARCHAR(255) NULL");
    echo "✓ courses.date_range agregado.\n";
} catch (PDOException $e) {
    echo "→ courses.date_range: " . $e->getMessage() . "\n";
}

// Dirección del curso
try {
    $pdo->exec("ALTER TABLE courses ADD COLUMN address VARCHAR(255) NULL DEFAULT 'Av. Tobalaba 1621, Providencia, Santiago'");
    echo "✓ courses.address agregado (default: Av. Tobalaba 1621).\n";
} catch (PDOException $e) {
    echo "→ courses.address: " . $e->getMessage() . "\n";
}

echo "\nMigración v8 completada.\n";
