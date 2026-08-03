<?php
// ═══════════════════════════════════════════════
// Selcap AV — API REST para integraciones (n8n)
// ═══════════════════════════════════════════════
// Autenticación: header `Authorization: Bearer <SELCAP_API_KEY>`
// La key vive en includes/api-key.php (NO versionado).
//
// Endpoints:
//   GET  api.php?action=check_user&email=x
//        → { ok, exists, user: {id, email, first_name, last_name, is_active} }
//
//   POST api.php?action=create_user
//        Body JSON: { email, first_name, last_name, rut? }
//        → { ok, user_id, email, password (temporal, se devuelve 1 sola vez) }
//
//   POST api.php?action=enroll
//        Body JSON: { email, sku }
//        → { ok, course_id, course_title, already_enrolled }
//
//   POST api.php?action=ensure_enroll
//        Body JSON: { email, first_name, last_name, sku, rut? }
//        → crea usuario si falta + matricula.
//        → { ok, user_id, created, password?, course_id, course_title, already_enrolled }
// ═══════════════════════════════════════════════

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api-key.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function apiError(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

// ── Autenticación ──
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? '';
$token = preg_replace('/^Bearer\s+/i', '', trim($authHeader));

if (!defined('SELCAP_API_KEY') || SELCAP_API_KEY === '' || !hash_equals(SELCAP_API_KEY, $token)) {
    apiError('No autorizado.', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$body = json_decode(file_get_contents('php://input') ?: '', true) ?? [];

$pdo = db();
$adminId = null; // acciones de sistema (sin sesión) — NULL cumple la FK

// Helper: log seguro (nunca rompe la respuesta)
function apiAudit(int|string|null $userId, string $action, string $entity, int|string|null $entityId, array $details = []): void
{
    try {
        $pdo = db();
        $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId ? (int) $userId : null, $action, $entity, $entityId, json_encode($details), $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {
        // nunca romper la respuesta por el log
    }
}

// ── check_user ──
if ($action === 'check_user' && $method === 'GET') {
    $email = strtolower(trim($_GET['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) apiError('Email inválido.');

    $stmt = $pdo->prepare('SELECT id, email, first_name, last_name, role, is_active FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    echo json_encode(['ok' => true, 'exists' => (bool)$user, 'user' => $user]);
    exit;
}

// ── create_user ──
if ($action === 'create_user' && $method === 'POST') {
    $email = strtolower(trim($body['email'] ?? ''));
    $firstName = trim($body['first_name'] ?? '');
    $lastName = trim($body['last_name'] ?? '');
    $rut = trim($body['rut'] ?? '') ?: null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) apiError('Email inválido.');
    if ($firstName === '' || $lastName === '') apiError('first_name y last_name son obligatorios.');

    $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$email]);
    if ($check->fetch()) apiError('El usuario ya existe. Usa enroll o ensure_enroll.', 409);

    // Password temporal segura
    $password = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 10);
    $hash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, role, rut, is_active) VALUES (?, ?, ?, ?, "student", ?, 1)');
        $stmt->execute([$email, $hash, $firstName, $lastName, $rut]);
        $userId = (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) apiError('El usuario ya existe.', 409);
        apiError('Error creando usuario: ' . $e->getMessage(), 500);
    }

    apiAudit($adminId, 'api_user_created', 'user', $userId, ['email' => $email, 'via' => 'n8n']);

    echo json_encode([
        'ok' => true,
        'user_id' => $userId,
        'email' => $email,
        'password' => $password, // temporal — enviar por correo
        'message' => 'Usuario creado.',
    ]);
    exit;
}

// ── enroll ──
if ($action === 'enroll' && $method === 'POST') {
    $email = strtolower(trim($body['email'] ?? ''));
    $sku = trim($body['sku'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) apiError('Email inválido.');
    if ($sku === '') apiError('sku es obligatorio.');

    $userStmt = $pdo->prepare('SELECT id, is_active FROM users WHERE email = ?');
    $userStmt->execute([$email]);
    $user = $userStmt->fetch();
    if (!$user) apiError('Usuario no existe. Créalo primero o usa ensure_enroll.', 404);
    if (!$user['is_active']) apiError('Usuario inactivo.', 403);

    $courseStmt = $pdo->prepare('SELECT id, title, status FROM courses WHERE sku = ?');
    $courseStmt->execute([$sku]);
    $course = $courseStmt->fetch();
    if (!$course) apiError("No existe curso con SKU '{$sku}'.", 404);
    if ($course['status'] !== 'published') apiError('El curso no está publicado.', 409);

    $enrStmt = $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)');
    $enrStmt->execute([$user['id'], $course['id']]);
    $already = $enrStmt->rowCount() === 0;

    apiAudit($adminId, 'api_student_enrolled', 'enrollment', $course['id'], ['user_id' => $user['id'], 'email' => $email, 'via' => 'n8n']);

    echo json_encode([
        'ok' => true,
        'user_id' => (int) $user['id'],
        'course_id' => (int) $course['id'],
        'course_title' => $course['title'],
        'already_enrolled' => $already,
    ]);
    exit;
}

// ── ensure_enroll (combo) ──
if ($action === 'ensure_enroll' && $method === 'POST') {
    $email = strtolower(trim($body['email'] ?? ''));
    $firstName = trim($body['first_name'] ?? '');
    $lastName = trim($body['last_name'] ?? '');
    $rut = trim($body['rut'] ?? '') ?: null;
    $sku = trim($body['sku'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) apiError('Email inválido.');
    if ($sku === '') apiError('sku es obligatorio.');

    $courseStmt = $pdo->prepare('SELECT id, title, status FROM courses WHERE sku = ?');
    $courseStmt->execute([$sku]);
    $course = $courseStmt->fetch();
    if (!$course) apiError("No existe curso con SKU '{$sku}'.", 404);
    if ($course['status'] !== 'published') apiError('El curso no está publicado.', 409);

    $userStmt = $pdo->prepare('SELECT id, is_active FROM users WHERE email = ?');
    $userStmt->execute([$email]);
    $user = $userStmt->fetch();

    $created = false;
    $password = null;
    $userId = null;

    if ($user) {
        if (!$user['is_active']) apiError('Usuario inactivo.', 403);
        $userId = (int) $user['id'];
    } else {
        if ($firstName === '' || $lastName === '') apiError('Usuario nuevo requiere first_name y last_name.');
        $password = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 10);
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, role, rut, is_active) VALUES (?, ?, ?, ?, "student", ?, 1)')
            ->execute([$email, $hash, $firstName, $lastName, $rut]);
        $userId = (int) $pdo->lastInsertId();
        $created = true;
    }

    $enrStmt = $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)');
    $enrStmt->execute([$userId, $course['id']]);
    $already = $enrStmt->rowCount() === 0;

    apiAudit($adminId, $created ? 'api_user_created' : 'api_student_enrolled', $created ? 'user' : 'enrollment',
        $created ? $userId : $course['id'],
        ['user_id' => $userId, 'email' => $email, 'sku' => $sku, 'via' => 'n8n']);

    echo json_encode([
        'ok' => true,
        'user_id' => $userId,
        'created' => $created,
        'password' => $password, // solo si created
        'course_id' => (int) $course['id'],
        'course_title' => $course['title'],
        'already_enrolled' => $already,
        'message' => $created ? 'Usuario creado y matriculado.' : ($already ? 'Usuario ya estaba matriculado.' : 'Usuario matriculado.'),
    ]);
    exit;
}

apiError('Acción no válida.', 404);
