<?php
// Endpoint AJAX: matriculados de un curso → JSON
// GET  /admin/course-students.php?course_id=X          → listado
// POST /admin/course-students.php (JSON)               → desmatricular
//   body: { "action": "unenroll", "course_id": X, "user_id": Y }
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pdo = db();

// ── POST: desmatricular ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
    if (($input['action'] ?? '') !== 'unenroll') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        exit;
    }
    $courseId = (int)($input['course_id'] ?? 0);
    $userId   = (int)($input['user_id'] ?? 0);
    if ($courseId <= 0 || $userId <= 0) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'course_id y user_id requeridos']);
        exit;
    }

    // Confirmar que la matrícula existe antes de borrar (evita sorpresas)
    $check = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
    $check->execute([$userId, $courseId]);
    if (!$check->fetch()) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Matrícula no encontrada']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM enrollments WHERE user_id = ? AND course_id = ?');
    $stmt->execute([$userId, $courseId]);

    // Auditoría (nunca rompe la respuesta)
    try {
        $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$_SESSION['user_id'] ?? null, 'unenroll', 'enrollment', $courseId,
                json_encode(['user_id' => $userId, 'course_id' => $courseId, 'source' => 'modal_cursos']),
                $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) { /* no romper */ }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'course_id' => $courseId, 'user_id' => $userId]);
    exit;
}

// ── GET: listado ──
$courseId = (int)($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'course_id requerido']);
    exit;
}

$pdo = db();

// Curso (título para el modal)
$courseStmt = $pdo->prepare('SELECT id, title FROM courses WHERE id = ?');
$courseStmt->execute([$courseId]);
$course = $courseStmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Curso no encontrado']);
    exit;
}

// Matriculados con datos de usuario
$stmt = $pdo->prepare('
    SELECT u.id, u.first_name, u.last_name, u.email, u.rut, u.is_active,
           e.enrolled_at, e.status
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    WHERE e.course_id = ?
    ORDER BY u.last_name, u.first_name
');
$stmt->execute([$courseId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($students as &$s) {
    $s['full_name'] = trim($s['first_name'] . ' ' . $s['last_name']);
    $s['enrolled_at'] = $s['enrolled_at'] ? date('d/m/Y', strtotime($s['enrolled_at'])) : '';
}
unset($s);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'course'  => $course,
    'students' => $students,
    'total'   => count($students),
]);
