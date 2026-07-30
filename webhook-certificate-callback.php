<?php
// ═══════════════════════════════════════════════
// Webhook Callback — Recibe PDF de certificado desde n8n
// ═══════════════════════════════════════════════
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json');

// ── Solo POST ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── Validar secret ──
$secret = $_SERVER['HTTP_X_CALLBACK_SECRET']
    ?? $_SERVER['HTTP_X_CALLBACK_SECRET']
    ?? ($_POST['callback_secret'] ?? '');

if (!$secret || $secret !== N8N_CERT_CALLBACK_SECRET) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Obtener datos ──
$attemptId = (int)($_POST['attempt_id'] ?? 0);
$n8nExecutionId = $_POST['n8n_execution_id'] ?? null;

if (!$attemptId) {
    http_response_code(400);
    echo json_encode(['error' => 'attempt_id is required']);
    exit;
}

$pdo = db();

// ── Verificar que existe el registro de certificado ──
$certStmt = $pdo->prepare('SELECT * FROM certificates WHERE attempt_id = ?');
$certStmt->execute([$attemptId]);
$cert = $certStmt->fetch();

if (!$cert) {
    http_response_code(404);
    echo json_encode(['error' => 'Certificate record not found for attempt_id ' . $attemptId]);
    exit;
}

// ── Verificar que se envió un archivo PDF ──
if (empty($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    // Intentar leer body crudo si no vino como multipart
    $rawBody = file_get_contents('php://input');

    // Si n8n envía el resultado como JSON con error
    $jsonData = json_decode($rawBody, true);
    if (!empty($jsonData['error'])) {
        $pdo->prepare('UPDATE certificates SET status = ?, error_message = ?, n8n_execution_id = ? WHERE id = ?')
            ->execute(['error', $jsonData['error'], $n8nExecutionId, $cert['id']]);
        http_response_code(200);
        echo json_encode(['status' => 'error_recorded']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'No PDF file received. Expected multipart field "pdf_file".']);
    exit;
}

// ── Crear carpeta de certificados si no existe ──
$certsDir = UPLOADS_DIR . '/certificados';
if (!is_dir($certsDir)) {
    mkdir($certsDir, 0755, true);
}

// ── Guardar el PDF ──
$folio = $cert['folio'];
$filename = "cert_{$folio}.pdf";
$filepath = $certsDir . '/' . $filename;

if (!move_uploaded_file($_FILES['pdf_file']['tmp_name'], $filepath)) {
    $pdo->prepare('UPDATE certificates SET status = ?, error_message = ? WHERE id = ?')
        ->execute(['error', 'Failed to save PDF file on server.', $cert['id']]);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save PDF file']);
    exit;
}

// ── Actualizar registro ──
$relativeUrl = 'uploads/certificados/' . $filename;
$pdo->prepare('UPDATE certificates SET status = ?, certificate_url = ?, n8n_execution_id = ?, generated_at = NOW(), error_message = NULL WHERE id = ?')
    ->execute(['ready', $relativeUrl, $n8nExecutionId, $cert['id']]);

// ── Auditoría ──
$pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([
        $cert['user_id'],
        'certificate_generated',
        'certificate',
        $cert['id'],
        json_encode(['folio' => $folio, 'file' => $filename, 'source' => 'n8n']),
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

http_response_code(200);
echo json_encode([
    'status'          => 'success',
    'certificate_id'  => $cert['id'],
    'folio'           => $folio,
    'file'            => $relativeUrl,
]);
