<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = db();
$stmt = $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)');
$stmt->execute([$_SESSION['user_id'], ACTIVE_COURSE_ID]);

header('Location: ' . BASE_URL . '/dashboard.php');
