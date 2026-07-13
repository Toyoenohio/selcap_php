<?php
// test_env.php — Buscar variables de entorno en todos lados
echo "<h3>\$_ENV</h3><pre>";
print_r($_ENV);
echo "</pre>";

echo "<h3>\$_SERVER (filtrado DB)</h3><pre>";
foreach ($_SERVER as $k => $v) {
    if (stripos($k, 'DB') !== false || stripos($k, 'MYSQL') !== false || stripos($k, 'PASS') !== false) {
        echo "$k = $v\n";
    }
}
echo "</pre>";

echo "<h3>getenv() individual</h3><pre>";
echo "DB_HOST = " . var_export(getenv('DB_HOST'), true) . "\n";
echo "DB_NAME = " . var_export(getenv('DB_NAME'), true) . "\n";
echo "DB_USER = " . var_export(getenv('DB_USER'), true) . "\n";
echo "DB_PASS = " . var_export(getenv('DB_PASS'), true) . "\n";
echo "</pre>";

echo "<h3>Apache getenv</h3><pre>";
echo "DB_HOST = " . var_export(apache_getenv('DB_HOST'), true) . "\n";
echo "DB_NAME = " . var_export(apache_getenv('DB_NAME'), true) . "\n";
echo "</pre>";
