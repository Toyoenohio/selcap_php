<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/includes/db.php';
$pdo = db();

echo "=== Evaluations table columns ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM evaluations")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  {$col['Field']} ({$col['Type']}) Null:{$col['Null']} Key:{$col['Key']}\n";
}

echo "\n=== Evaluations with NULL course_id ===\n";
$null = $pdo->query("SELECT COUNT(*) as cnt FROM evaluations WHERE course_id IS NULL")->fetch();
echo "  {$null['cnt']} evaluaciones sin course_id\n";

echo "\n=== All evaluations ===\n";
$all = $pdo->query("SELECT id, title, section_id, course_id FROM evaluations")->fetchAll(PDO::FETCH_ASSOC);
foreach ($all as $e) {
    echo "  #{$e['id']} '{$e['title']}' section_id={$e['section_id']} course_id={$e['course_id']}\n";
}
