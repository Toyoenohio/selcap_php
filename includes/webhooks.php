<?php
// ═══════════════════════════════════════════════
// Selcap AV — Webhooks de notificación (n8n / automatizaciones)
// ═══════════════════════════════════════════════
require_once __DIR__ . '/config.php';

/**
 * Notifica a un webhook externo (automatización de email de bienvenida,
 * CRM, etc.) cuando un alumno es matriculado en un curso.
 *
 * Fire-and-forget: nunca bloquea ni rompe el flujo del panel.
 * Timeout corto (2s) — si el webhook no responde, se ignora.
 *
 * @param int    $userId   ID del usuario matriculado
 * @param int    $courseId ID del curso
 * @param string $source   Origen: 'manual' (panel asignar) | 'bulk' (matrícula masiva)
 */
function notifyEnrollmentToWebhook(int $userId, int $courseId, string $source = 'manual'): void
{
    try {
        $url = getenv('N8N_WELCOME_WEBHOOK_URL') ?: (defined('N8N_WELCOME_WEBHOOK_URL') ? N8N_WELCOME_WEBHOOK_URL : '');
        if ($url === '') return; // no configurado → no hacer nada

        $pdo = db();
        $stmt = $pdo->prepare('
            SELECT u.id as user_id, u.first_name, u.last_name, u.email, u.rut,
                   c.id as course_id, c.title as course_title, c.sku
            FROM users u, courses c
            WHERE u.id = ? AND c.id = ?
        ');
        $stmt->execute([$userId, $courseId]);
        $data = $stmt->fetch();
        if (!$data) return;

        $payload = [
            'event'          => 'student_enrolled',
            'source'         => $source,
            'student'        => [
                'user_id'    => (int)$data['user_id'],
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'full_name'  => trim($data['first_name'] . ' ' . $data['last_name']),
                'email'      => $data['email'],
                'rut'        => $data['rut'] ?: '',
            ],
            'course'         => [
                'course_id'    => (int)$data['course_id'],
                'title'        => $data['course_title'],
                'sku'          => $data['sku'] ?: '',
            ],
            'enrolled_at'    => date('c'),
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 2,          // no bloquear el panel
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    } catch (Exception $e) {
        // nunca romper el flujo del panel por un webhook caído
    }
}
