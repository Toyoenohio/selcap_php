<?php
// ═══════════════════════════════════════════════
// Selcap AV — Helper para comunicación con n8n
// ═══════════════════════════════════════════════
require_once __DIR__ . '/config.php';

/**
 * Envía los datos del certificado a n8n para generar el PDF.
 *
 * @param int $attemptId ID del intento aprobado (evaluation_attempts.id)
 * @return array ['success' => bool, 'message' => string, 'certificate_id' => int|null]
 */
function sendCertificateToN8N(int $attemptId): array
{
    $pdo = db();

    // ── Obtener todos los datos necesarios ──
    $stmt = $pdo->prepare('
        SELECT ea.id as attempt_id, ea.score, ea.submitted_at,
               e.title as eval_title, e.hours as eval_hours, e.date_range as eval_date_range,
               c.id as course_id, c.title as course_title, c.hours, c.date_range, c.address,
               u.id as user_id, u.first_name, u.last_name, u.rut, u.email
        FROM evaluation_attempts ea
        JOIN evaluations e ON ea.evaluation_id = e.id
        JOIN courses c ON e.course_id = c.id
        JOIN users u ON ea.user_id = u.id
        WHERE ea.id = ? AND ea.passed = 1
    ');
    $stmt->execute([$attemptId]);
    $data = $stmt->fetch();

    if (!$data) {
        return ['success' => false, 'message' => 'Intento no encontrado o no aprobado.', 'certificate_id' => null];
    }

    // ── Generar folio ──
    $folio = str_pad($attemptId, 5, '0', STR_PAD_LEFT);

    // ── Crear o actualizar registro en certificates ──
    $existStmt = $pdo->prepare('SELECT id FROM certificates WHERE attempt_id = ?');
    $existStmt->execute([$attemptId]);
    $existing = $existStmt->fetch();

    if ($existing) {
        // Reintentar: resetear estado
        $pdo->prepare('UPDATE certificates SET status = ?, error_message = NULL, n8n_execution_id = NULL WHERE id = ?')
            ->execute(['pending', $existing['id']]);
        $certId = (int)$existing['id'];
    } else {
        $pdo->prepare('INSERT INTO certificates (attempt_id, user_id, course_id, folio, status) VALUES (?, ?, ?, ?, ?)')
            ->execute([$attemptId, $data['user_id'], $data['course_id'], $folio, 'pending']);
        $certId = (int)$pdo->lastInsertId();
    }

    // ── Preparar URLs ──
    $verifyUrl = 'https://aula.selcap.cl/verificar.php?id=' . $attemptId;
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($verifyUrl);
    $callbackUrl = 'https://aula.selcap.cl/webhook-certificate-callback.php';

    // ── Payload para n8n ──
    $payload = [
        'attempt_id'     => $attemptId,
        'certificate_id' => $certId,
        'folio'          => $folio,
        'student_name'   => trim($data['first_name'] . ' ' . $data['last_name']),
        'student_rut'    => $data['rut'] ?: 'XX.XXX.XXX-X',
        'student_email'  => $data['email'],
        'course_title'   => $data['course_title'],
        'eval_title'     => $data['eval_title'],
        'score'          => round($data['score']),
        'date_range'     => $data['eval_date_range'] ?: ($data['date_range'] ?: ''),
        'hours'          => $data['eval_hours'] !== null ? (int)$data['eval_hours'] : ((int)$data['hours'] ?: 0),
        'address'        => $data['address'] ?: 'Av. Tobalaba 1621, Providencia, Santiago.',
        'submitted_at'   => $data['submitted_at'],
        'qr_url'         => $qrUrl,
        'verify_url'     => $verifyUrl,
        'callback_url'   => $callbackUrl,
        'callback_secret' => N8N_CERT_CALLBACK_SECRET,
    ];

    // ── Enviar POST a n8n ──
    $ch = curl_init(N8N_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,          // no bloquear más de 15s
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // ── Manejar respuesta ──
    if ($curlError) {
        $pdo->prepare('UPDATE certificates SET status = ?, error_message = ? WHERE id = ?')
            ->execute(['error', 'cURL error: ' . $curlError, $certId]);
        return ['success' => false, 'message' => 'Error de conexión con n8n: ' . $curlError, 'certificate_id' => $certId];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        // n8n recibió correctamente — el PDF llegará vía callback
        $pdo->prepare('UPDATE certificates SET status = ? WHERE id = ?')
            ->execute(['processing', $certId]);

        // Intentar extraer execution_id si n8n lo devuelve
        $responseData = json_decode($response, true);
        if (!empty($responseData['executionId'])) {
            $pdo->prepare('UPDATE certificates SET n8n_execution_id = ? WHERE id = ?')
                ->execute([$responseData['executionId'], $certId]);
        }

        return ['success' => true, 'message' => 'Certificado en proceso de generación.', 'certificate_id' => $certId];
    }

    // Error HTTP
    $pdo->prepare('UPDATE certificates SET status = ?, error_message = ? WHERE id = ?')
        ->execute(['error', "HTTP {$httpCode}: {$response}", $certId]);
    return ['success' => false, 'message' => "n8n respondió con código {$httpCode}", 'certificate_id' => $certId];
}

/**
 * Obtiene el estado del certificado para un intento dado.
 *
 * @param int $attemptId
 * @return array|false  Fila de certificates o false si no existe
 */
function getCertificateStatus(int $attemptId)
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM certificates WHERE attempt_id = ?');
    $stmt->execute([$attemptId]);
    return $stmt->fetch();
}
