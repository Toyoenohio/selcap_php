<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = db();
$courseId = (int) ($_POST['course_id'] ?? ACTIVE_COURSE_ID);
$stmt = $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)');
$stmt->execute([$_SESSION['user_id'], $courseId]);

header('Location: ' . BASE_URL . '/curso.php?id=' . $courseId);
