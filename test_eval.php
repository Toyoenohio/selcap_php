<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/includes/config.php';
$pdo = db();

echo "=== Testing evaluation INSERT ===\n";

// Simulate creating an evaluation for course 3
$courseId = 3;
$sectionId = null;  // sin sección
$title = 'TEST EVALUATION ' . time();
$description = 'Test description';

try {
    $stmt = $pdo->prepare('INSERT INTO evaluations (course_id, section_id, title, description, max_attempts, passing_score, sort_order) VALUES (?, ?, ?, ?, 1, ?, ?)');
    $stmt->execute([$courseId, $sectionId, $title, $description, 80, 0]);
    $newId = $pdo->lastInsertId();
    echo "✓ Evaluation created! ID: $newId\n";
    
    // Clean up
    $pdo->prepare('DELETE FROM evaluations WHERE id=?')->execute([$newId]);
    echo "✓ Cleaned up.\n";
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Table structure ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM evaluations")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  {$col['Field']} — Type: {$col['Type']}, Null: {$col['Null']}, Default: " . var_export($col['Default'], true) . "\n";
}
