<?php
// test_db.php — Diagnóstico de conexión y entorno
require_once __DIR__ . '/includes/config.php';

echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";
echo "DB_USER: " . DB_USER . "<br>";
echo "BASE_URL: " . BASE_URL . "<br>";

try {
    $pdo = db();
    echo "✓ Conexión OK<br>";

    // Ver si columnas ya existen
    $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'rut'")->fetchAll();
    echo "users.rut: " . (count($cols) ? 'EXISTE' : 'NO EXISTE') . "<br>";

    $cols = $pdo->query("SHOW COLUMNS FROM courses LIKE 'hours'")->fetchAll();
    echo "courses.hours: " . (count($cols) ? 'EXISTE' : 'NO EXISTE') . "<br>";

    $cols = $pdo->query("SHOW COLUMNS FROM courses LIKE 'date_range'")->fetchAll();
    echo "courses.date_range: " . (count($cols) ? 'EXISTE' : 'NO EXISTE') . "<br>";

    $cols = $pdo->query("SHOW COLUMNS FROM courses LIKE 'address'")->fetchAll();
    echo "courses.address: " . (count($cols) ? 'EXISTE' : 'NO EXISTE') . "<br>";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}
