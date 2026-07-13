<?php
// create_admin.php — Crear usuario admin
// Uso: php create_admin.php [contraseña]
// Si no se pasa contraseña, se genera una aleatoria y se muestra.

require_once __DIR__ . '/includes/config.php';
$pdo = db();

$email = 'javiera@selcap.cl';
$firstName = 'Javiera';
$lastName = 'Admin';

// Contraseña por argumento o aleatoria
$password = $argv[1] ?? bin2hex(random_bytes(6));

// Verificar si ya existe
$check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) {
    $pdo->prepare('UPDATE users SET role = "admin" WHERE email = ?')->execute([$email]);
    echo "✓ Usuario {$email} ya existía — actualizado a admin.\n";
} else {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, role, is_active) VALUES (?, ?, ?, ?, "admin", 1)')
        ->execute([$email, $hash, $firstName, $lastName]);
    echo "✓ Usuario admin creado: {$email}\n";
    echo "  Contraseña: {$password}\n";
    echo "  ⚠️  Guardala — no se almacena en ningún lado.\n";
}

echo "\nListo.\n";
