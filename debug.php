<?php
// Subir como debug.php a public_html/ y abrir en navegador
// Borrar después de usar

echo '<h2>Diagnóstico Selcap AV</h2>';
echo '<pre>';

echo 'PHP Version: ' . phpversion() . "\n";
echo 'PDO drivers: ' . implode(', ', PDO::getAvailableDrivers()) . "\n";
echo 'pdo_mysql: ' . (in_array('mysql', PDO::getAvailableDrivers()) ? '✓ OK' : '✗ FALTA — instalá php-pdo-mysql en cPanel → Select PHP Version') . "\n";
echo 'Sesiones: ' . (function_exists('session_start') ? '✓ OK' : '✗ FALTA') . "\n";
echo 'bcrypt (password_hash): ' . (function_exists('password_hash') ? '✓ OK' : '✗ FALTA') . "\n";
echo 'file_uploads: ' . (ini_get('file_uploads') ? '✓ OK' : '✗ FALTA') . "\n";
echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . "\n";
echo 'post_max_size: ' . ini_get('post_max_size') . "\n";

echo "\n--- Probando includes/config.php ---\n";
try {
    require __DIR__ . '/includes/config.php';
    echo "config.php: ✓ Cargado\n";
    echo "DB_HOST: " . DB_HOST . "\n";
    echo "DB_NAME: " . DB_NAME . "\n";
    echo "DB_USER: " . DB_USER . "\n";
    
    echo "\n--- Probando conexión MySQL ---\n";
    try {
        $pdo = db();
        echo "Conexión: ✓ OK\n";
        echo "Server: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
    } catch (PDOException $e) {
        echo "✗ ERROR de conexión: " . $e->getMessage() . "\n";
    }
} catch (Throwable $e) {
    echo "✗ ERROR en config.php: " . $e->getMessage() . "\n";
}

echo '</pre>';
