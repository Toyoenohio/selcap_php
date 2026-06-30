<?php
/**
 * Webhook — Crear alumno desde n8n / WooCommerce
 * 
 * POST /webhook-create-student.php
 * Headers: X-API-Key: <WEBHOOK_API_KEY>
 * Body (JSON): {
 *     "email": "...",
 *     "first_name": "...",
 *     "last_name": "...",
 *     "sku": "FUND-SEG-IND"          // opcional — si no viene, usa curso activo
 * }
 * 
 * Contraseña automática: Selcap2026*
 * Enrola al curso vinculado al SKU recibido.
 */

require_once __DIR__ . '/includes/config.php';

// ── Autenticación por API Key ──
define('WEBHOOK_API_KEY', getenv('WEBHOOK_API_KEY') ?: 'selcap-wh-2026');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== WEBHOOK_API_KEY) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// ── Solo POST ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── Parsear JSON ──
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['email']) || empty($input['first_name']) || empty($input['last_name'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Missing required fields: email, first_name, last_name']);
    exit;
}

$email = trim(strtolower($input['email']));
$firstName = trim($input['first_name']);
$lastName = trim($input['last_name']);
$sku = trim($input['sku'] ?? '');
$password = 'Selcap2026*';

try {
    $pdo = db();

    // ── Determinar curso ──
    if ($sku) {
        $courseStmt = $pdo->prepare('SELECT id, title FROM courses WHERE sku = ? AND status = "published"');
        $courseStmt->execute([$sku]);
        $course = $courseStmt->fetch();

        if (!$course) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => "Course not found for SKU: $sku"]);
            exit;
        }
        $courseId = (int)$course['id'];
        $courseTitle = $course['title'];
    } else {
        $courseId = ACTIVE_COURSE_ID;
        $courseTitle = 'Curso por defecto';
    }

    // Verificar si ya existe
    $check = $pdo->prepare('SELECT id, is_active FROM users WHERE email = ?');
    $check->execute([$email]);
    $existing = $check->fetch();

    if ($existing) {
        // Reactivar si estaba inactivo
        if (!$existing['is_active']) {
            $pdo->prepare('UPDATE users SET is_active = 1, first_name = ?, last_name = ? WHERE id = ?')
                ->execute([$firstName, $lastName, $existing['id']]);
            $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$existing['id'], 'student_reactivated_webhook', 'user', $existing['id'], json_encode(['source' => 'n8n', 'sku' => $sku]), $_SERVER['REMOTE_ADDR'] ?? '']);
            $userId = $existing['id'];
        } else {
            $userId = $existing['id'];
            // Verificar enrolamiento al curso del SKU
            $enrCheck = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
            $enrCheck->execute([$userId, $courseId]);
            if (!$enrCheck->fetch()) {
                $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)')->execute([$userId, $courseId]);
                $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$userId, 'student_enrolled_webhook', 'course', $courseId, json_encode(['source' => 'n8n', 'sku' => $sku]), $_SERVER['REMOTE_ADDR'] ?? '']);
            }
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'user_id' => $userId, 'course_id' => $courseId, 'course_title' => $courseTitle, 'note' => 'User already exists and is active. Enrolled if needed.']);
            exit;
        }
    } else {
        // Crear nuevo alumno
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES (?, ?, ?, ?, "student")');
        $stmt->execute([$email, $hash, $firstName, $lastName]);
        $userId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId, 'student_created_webhook', 'user', $userId, json_encode(['source' => 'n8n', 'email' => $email, 'name' => "$firstName $lastName", 'sku' => $sku]), $_SERVER['REMOTE_ADDR'] ?? '']);
    }

    // Enrolar al curso (por SKU o activo)
    $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)')->execute([$userId, $courseId]);
    $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$userId, 'student_enrolled_webhook', 'course', $courseId, json_encode(['source' => 'n8n', 'sku' => $sku]), $_SERVER['REMOTE_ADDR'] ?? '']);

    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'user_id' => $userId,
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'sku' => $sku ?: null,
        'course_id' => $courseId,
        'course_title' => $courseTitle,
        'password' => $password,
        'note' => 'Student created/updated and enrolled in course.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
