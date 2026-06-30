<?php
// Subir a public_html/reset_admin.php y abrir UNA SOLA VEZ
// Borrar inmediatamente después de usar

require __DIR__ . '/includes/config.php';

$hash = password_hash('admin123', PASSWORD_BCRYPT);
$pdo = db();
$pdo->prepare("UPDATE users SET password_hash = ? WHERE email = 'admin@selcap.com'")->execute([$hash]);

echo "✓ Password del admin reseteado a: admin123";
echo "\n\nBORRÁ ESTE ARCHIVO AHORA MISMO.";
