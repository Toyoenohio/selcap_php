<?php
// enroll_pep3.php — Crear usuarios e inscribir en curso PEP-3 (course_id=3)
require_once __DIR__ . '/includes/config.php';
$pdo = db();

$courseId = 3;
$defaultPassword = 'Selcap2026*';
$hash = password_hash($defaultPassword, PASSWORD_BCRYPT);

$users = [
    ['María José', 'Orrego', 'orrego.mj@gmail.com', '15830277-2'],
    ['Danaes Belén', 'Albornoz Zenteno', 'flga.danaesalbornozz@gmail.com', '19127625-6'],
    ['Vanessa', 'Araya Álvarez', 'v.arayalvarez@gmail.com', '19952344-9'],
    ['Mikhaella Alejandra', 'Jara Zúñiga', 'to.mikhaella@gmail.com', '20704786-4'],
    ['Constanza Belén', 'Godoy Ojeda', 'to.constanzagodoy@gmail.com', '20572226-2'],
    ['Valentina', 'Ibañez llancaqueo', 'v.ibaezll@gmail.com', '19642483-0'],
    ['Camila', 'Rojas Rojas', 'rojasrojascf@gmail.com', '18476554-3'],
    ['Yosselyn', 'Ramirez Espinoza', 'Yosseramirez@gmail.com', '18218425-k'],
];

$created = 0;
$enrolled = 0;
$skipped = 0;

foreach ($users as $u) {
    [$firstName, $lastName, $email, $rut] = $u;

    $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$email]);
    $existing = $check->fetch();

    if ($existing) {
        $userId = (int)$existing['id'];
        echo "→ $email ya existe (ID $userId)\n";
        $skipped++;
    } else {
        $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, role, rut, is_active) VALUES (?, ?, ?, ?, "student", ?, 1)')
            ->execute([$email, $hash, $firstName, $lastName, $rut]);
        $userId = (int)$pdo->lastInsertId();
        echo "✓ Creado: $firstName $lastName — $email (ID $userId)\n";
        $created++;
    }

    // Inscribir (evitar duplicados con IGNORE)
    $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)')
        ->execute([$userId, $courseId]);
    $enrolled++;
}

echo "\n---\n";
echo "Creados: $created | Ya existían: $skipped | Inscritos en PEP-3: $enrolled\n";
echo "Contraseña por defecto: $defaultPassword\n";
