<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = currentUser();
$pdo = db();

// Auto-inscripción pública deshabilitada: las matrículas se crean por pago
// (WooCommerce → webhook-create-student.php) o por el admin. Bloquear a
// estudiantes para que no se inscriban a un curso sin haber pagado.
if (!$user || $user['role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/mis-cursos.php');
    exit;
}

$courseId = (int) ($_POST['course_id'] ?? ACTIVE_COURSE_ID);
$stmt = $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)');
$stmt->execute([$_SESSION['user_id'], $courseId]);

header('Location: ' . BASE_URL . '/curso.php?id=' . $courseId);
