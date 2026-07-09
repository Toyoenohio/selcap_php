<?php
require_once __DIR__ . '/includes/db.php';
$pdo = db();

echo "=== Evaluations table columns ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM evaluations")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  {$col['Field']} ({$col['Type']}) Null:{$col['Null']} Key:{$col['Key']}\n";
}

echo "\n=== Foreign keys on evaluations ===\n";
$fks = $pdo->query("
    SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'evaluations'
    AND REFERENCED_TABLE_NAME IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($fks as $fk) {
    echo "  {$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}({$fk['REFERENCED_COLUMN_NAME']})\n";
}
if (empty($fks)) echo "  (ninguna)\n";

echo "\n=== Evaluations with NULL course_id ===\n";
$null = $pdo->query("SELECT COUNT(*) as cnt FROM evaluations WHERE course_id IS NULL")->fetch();
echo "  {$null['cnt']} evaluaciones sin course_id\n";

echo "\n=== All evaluations ===\n";
$all = $pdo->query("SELECT id, title, section_id, course_id FROM evaluations")->fetchAll(PDO::FETCH_ASSOC);
foreach ($all as $e) {
    echo "  #{$e['id']} '{$e['title']}' section_id={$e['section_id']} course_id={$e['course_id']}\n";
}
