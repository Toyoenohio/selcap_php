<?php
// Selcap AV — Configuración (producción SiteGround)

// ── Base de datos ──
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbekvjvb52iehb');
define('DB_USER', 'uty2jzjeamkly');
define('DB_PASS', 'be73yavgg5ox');
define('DB_CHARSET', 'utf8mb4');

// ── Rutas ──
define('BASE_URL', '');
define('UPLOADS_DIR', __DIR__ . '/../uploads');
define('UPLOADS_URL', '/uploads');

// ── Curso activo (MVP: un solo curso) ──
define('ACTIVE_COURSE_ID', 1);

// ── n8n — Generación de certificados PDF ──
define('N8N_WEBHOOK_URL', getenv('N8N_WEBHOOK_URL') ?: 'https://n8nciwok-n8n.wz1vdn.easypanel.host/webhook-test/selcap-certificados');
define('N8N_CERT_CALLBACK_SECRET', getenv('N8N_CERT_CALLBACK_SECRET') ?: 'selcap-cert-callback-2026');

// ── Conexión PDO ──
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
