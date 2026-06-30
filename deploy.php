<?php
/**
 * Selcap AV — Deploy Webhook
 * 
 * GitHub → este script → git pull automático.
 * 
 * Configurar en GitHub:
 *   Settings → Webhooks → Add webhook
 *   Payload URL: https://aula.selcap.cl/deploy.php
 *   Content type: application/json
 *   Secret: (el mismo que ponés abajo)
 *   Events: Just the push event
 */

// ── CONFIGURAR ──
$SECRET = 'cambiar-por-secreto-largo-y-aleatorio';
$REPO_DIR = __DIR__;           // directorio donde está el repo clonado
$BRANCH = 'main';

// ── Verificar firma de GitHub ──
$signature = 'sha256=' . hash_hmac('sha256', file_get_contents('php://input'), $SECRET);
if (!hash_equals($signature, $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '')) {
    http_response_code(403);
    die('Invalid signature');
}

// ── Ejecutar git pull ──
$output = [];
$exitCode = 0;
chdir($REPO_DIR);
exec("git pull origin {$BRANCH} 2>&1", $output, $exitCode);

// ── Limpiar cache de OPcache si existe ──
if (function_exists('opcache_reset')) {
    opcache_reset();
}

// ── Responder ──
header('Content-Type: application/json');
echo json_encode([
    'success' => $exitCode === 0,
    'output' => $output,
    'time' => date('c'),
]);
