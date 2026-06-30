<?php
/**
 * Webhook — Crear alumno desde n8n / WooCommerce
 * 
 * POST /webhook-create-student.php
 * Headers: X-API-Key: <WEBHOOK_API_KEY>
 * Body (JSON): {"email": "...", "first_name": "...", "last_name": "..."}
 * 
 * Contraseña automática: Selcap2026*
 * Auto-enról al curso activo.
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
$password = 'Selcap2026*';

try {
    $pdo = db();

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
                ->execute([$existing['id'], 'student_reactivated_webhook', 'user', $existing['id'], json_encode(['source' => 'n8n']), $_SERVER['REMOTE_ADDR'] ?? '']);
            $userId = $existing['id'];
        } else {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'user_id' => $existing['id'], 'note' => 'User already exists and is active']);
            exit;
        }
    } else {
        // Crear nuevo alumno
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES (?, ?, ?, ?, "student")');
        $stmt->execute([$email, $hash, $firstName, $lastName]);
        $userId = (int) $pdo->lastInsertId();

        // Auditoría
        $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId, 'student_created_webhook', 'user', $userId, json_encode(['source' => 'n8n', 'email' => $email, 'name' => "$firstName $lastName"]), $_SERVER['REMOTE_ADDR'] ?? '']);
    }

    // Auto-enroll al curso activo
    $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)')->execute([$userId, ACTIVE_COURSE_ID]);

    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'user_id' => $userId,
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'password' => $password,
        'note' => 'Student created/updated successfully. Auto-enrolled.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
