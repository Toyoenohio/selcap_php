<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/webhooks.php';
requireAdmin();

$pdo = db();
$msg = ''; $msgType = '';

// ── Acciones ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_student') {
        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $password = trim($_POST['password'] ?? 'Selcap2026*');

        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $msg = 'El email ya está registrado.'; $msgType = 'red';
        } elseif ($email && $firstName && $lastName) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $rut = trim($_POST['rut'] ?? '') ?: null;
            $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, role, rut) VALUES (?, ?, ?, ?, "student", ?)');
            $stmt->execute([$email, $hash, $firstName, $lastName, $rut]);
            $userId = (int) $pdo->lastInsertId();

            // Auto-enroll
            $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)')->execute([$userId, ACTIVE_COURSE_ID]);

            // Webhook de bienvenida (email automático vía n8n)
            notifyEnrollmentToWebhook($userId, ACTIVE_COURSE_ID, 'manual');

            // Auditoría
            $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$_SESSION['user_id'], 'student_created', 'user', $userId, json_encode(['email' => $email, 'name' => "$firstName $lastName"]), $_SERVER['REMOTE_ADDR'] ?? '']);

            $msg = "Alumno creado: $firstName $lastName"; $msgType = 'green';
        }
    } elseif ($_POST['action'] === 'update_student') {
        $id = (int)$_POST['id'];
        $email = trim($_POST['email'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $newPassword = trim($_POST['new_password'] ?? '');
        $rut = trim($_POST['rut'] ?? '') ?: null;

        $pdo->prepare('UPDATE users SET email = ?, first_name = ?, last_name = ?, is_active = ?, rut = ? WHERE id = ? AND role = "student"')
            ->execute([$email, $firstName, $lastName, $isActive, $rut, $id]);

        if ($newPassword) {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
        }

        $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$_SESSION['user_id'], 'student_updated', 'user', $id, json_encode(['email' => $email, 'active' => $isActive]), $_SERVER['REMOTE_ADDR'] ?? '']);

        $msg = 'Alumno actualizado.'; $msgType = 'blue';
    } elseif ($_POST['action'] === 'deactivate_student') {
        $id = (int)$_POST['id'];
        $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ? AND role = "student"')->execute([$id]);
        $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$_SESSION['user_id'], 'student_deactivated', 'user', $id, json_encode([]), $_SERVER['REMOTE_ADDR'] ?? '']);
        $msg = 'Alumno desactivado.'; $msgType = 'red';
    } elseif ($_POST['action'] === 'create_students_bulk') {
        // ── Carga masiva desde CSV ──
        if (empty($_FILES['csv_file']['tmp_name'])) {
            $msg = 'Seleccioná un archivo CSV.'; $msgType = 'red';
        } else {
            $contents = file_get_contents($_FILES['csv_file']['tmp_name']);
            $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents); // quitar BOM UTF-8
            $lines = preg_split('/\r\n|\r|\n/', trim($contents));
            $delimiter = (strpos($contents, ';') !== false) ? ';' : ',';

            $created = 0; $duplicates = 0; $errors = 0; $errorDetails = [];
            $defaultPassword = 'Selcap2026*';
            $hash = password_hash($defaultPassword, PASSWORD_BCRYPT);
            $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $insStmt = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, role, rut) VALUES (?, ?, ?, ?, "student", ?)');

            foreach ($lines as $lineNum => $line) {
                $line = trim($line);
                if ($line === '') continue;

                $cols = str_getcsv($line, $delimiter);
                // Formato: nombre, apellido, email [, rut]
                $firstName = trim($cols[0] ?? '');
                $lastName  = trim($cols[1] ?? '');
                $email     = strtolower(trim($cols[2] ?? ''));
                $rut       = trim($cols[3] ?? '') ?: null;

                // Saltar fila de encabezados si la primera columna no parece nombre
                if ($lineNum === 0 && in_array(strtolower($firstName), ['nombre', 'first_name', 'name', 'nombres'])) {
                    continue;
                }

                if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors++;
                    $errorDetails[] = "Línea " . ($lineNum + 1) . ": datos incompletos ('$line')";
                    continue;
                }

                $checkStmt->execute([$email]);
                if ($checkStmt->fetch()) {
                    $duplicates++;
                    continue;
                }

                try {
                    $insStmt->execute([$email, $hash, $firstName, $lastName, $rut]);
                    $created++;
                } catch (PDOException $e) {
                    $errors++;
                    $errorDetails[] = "Línea " . ($lineNum + 1) . ": " . $e->getMessage();
                }
            }

            if ($created > 0) {
                $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$_SESSION['user_id'], 'students_bulk_created', 'user', null, json_encode(['created' => $created, 'duplicates' => $duplicates, 'errors' => $errors]), $_SERVER['REMOTE_ADDR'] ?? '']);
            }

            $msg = "$created alumno(s) creado(s)." . ($duplicates ? " $duplicates ya existían." : '') . ($errors ? " $errors con error." : '');
            $msgType = ($errors && !$created) ? 'red' : 'green';
            $bulkErrors = $errorDetails;
        }
    }
}

// ── Lista de alumnos (todos, el filtrado es en vivo por JS) ──
$stmt = $pdo->prepare("SELECT u.*,
    (SELECT COUNT(*) FROM enrollments e WHERE e.user_id = u.id) as enrolled_courses,
    (SELECT COUNT(*) FROM evaluation_attempts ea WHERE ea.user_id = u.id AND ea.passed = 1) as passed_evals,
    (SELECT c.title FROM enrollments e2 JOIN courses c ON e2.course_id = c.id WHERE e2.user_id = u.id ORDER BY e2.enrolled_at DESC LIMIT 1) as last_course
    FROM users u WHERE u.role = 'student' ORDER BY u.created_at DESC LIMIT 500");
$stmt->execute();
$students = $stmt->fetchAll();

$total = count($students);
$activos = count(array_filter($students, fn($s) => $s['is_active']));
$inactivos = $total - $activos;
$conCursos = count(array_filter($students, fn($s) => $s['enrolled_courses'] > 0));

$pageTitle = 'Admin — Alumnos';
$currentPage = 'alumnos';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-extrabold text-neutral-900">Alumnos</h1>
      <p class="text-sm text-neutral-400 mt-0.5">Gestioná los estudiantes de la plataforma</p>
    </div>
    <div class="flex items-center gap-2 text-sm flex-wrap">
      <button onclick="openCreate()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2.5 rounded-xl font-semibold transition-colors shadow-sm shadow-primary-600/20 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Nuevo alumno
      </button>
      <button onclick="openBulk()" class="bg-white border border-neutral-200 hover:border-primary-300 hover:text-primary-600 text-neutral-700 px-4 py-2.5 rounded-xl font-semibold transition-colors flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
        Cargar CSV
      </button>
    </div>
  </div>
</div>

<?php if ($msg): ?>
  <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium border <?= $msgType === 'green' ? 'bg-green-50 text-green-700 border-green-200' : ($msgType === 'red' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-blue-50 text-blue-700 border-blue-200') ?>">
    <?= $msg ?>
    <?php if (!empty($bulkErrors)): ?>
      <details class="mt-2">
        <summary class="text-xs font-semibold cursor-pointer">Ver <?= count($bulkErrors) ?> detalle(s) de error</summary>
        <div class="mt-2 space-y-1 max-h-40 overflow-y-auto text-xs">
          <?php foreach ($bulkErrors as $e): ?><p>• <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        </div>
      </details>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-2xl border border-neutral-200 p-5">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
      </div>
      <div>
        <p class="text-2xl font-extrabold text-neutral-900"><?= $total ?></p>
        <p class="text-xs text-neutral-400 font-medium">Total alumnos</p>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-2xl border border-neutral-200 p-5">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <div>
        <p class="text-2xl font-extrabold text-neutral-900"><?= $activos ?></p>
        <p class="text-xs text-neutral-400 font-medium">Activos</p>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-2xl border border-neutral-200 p-5">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-red-50 text-red-500 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <div>
        <p class="text-2xl font-extrabold text-neutral-900"><?= $inactivos ?></p>
        <p class="text-xs text-neutral-400 font-medium">Inactivos</p>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-2xl border border-neutral-200 p-5">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
      </div>
      <div>
        <p class="text-2xl font-extrabold text-neutral-900"><?= $conCursos ?></p>
        <p class="text-xs text-neutral-400 font-medium">Con cursos</p>
      </div>
    </div>
  </div>
</div>

<!-- Buscador + filtros -->
<div class="bg-white rounded-2xl border border-neutral-200 p-4 mb-4 flex flex-wrap items-center gap-3">
  <div class="relative flex-1 min-w-[220px]">
    <svg class="w-4.5 h-4.5 w-5 h-5 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <input type="text" id="buscar" placeholder="Buscar por nombre, email o RUT..." autocomplete="off"
           class="w-full pl-11 pr-4 py-2.5 bg-neutral-50 border border-neutral-200 rounded-xl text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm">
  </div>
  <div class="flex items-center gap-2 text-sm">
    <button data-filter="todos" onclick="setFilter('todos')" class="filter-btn px-3.5 py-2 rounded-xl font-semibold transition-colors bg-primary-600 text-white">Todos</button>
    <button data-filter="activos" onclick="setFilter('activos')" class="filter-btn px-3.5 py-2 rounded-xl font-semibold transition-colors text-neutral-600 hover:bg-neutral-100">Activos</button>
    <button data-filter="inactivos" onclick="setFilter('inactivos')" class="filter-btn px-3.5 py-2 rounded-xl font-semibold transition-colors text-neutral-600 hover:bg-neutral-100">Inactivos</button>
    <button data-filter="conCursos" onclick="setFilter('conCursos')" class="filter-btn px-3.5 py-2 rounded-xl font-semibold transition-colors text-neutral-600 hover:bg-neutral-100">Con cursos</button>
  </div>
</div>

<!-- Tabla -->
<div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-neutral-50 border-b border-neutral-100">
        <tr>
          <th class="text-left px-5 py-3.5 font-semibold text-neutral-500 text-xs uppercase tracking-wider">Alumno</th>
          <th class="text-left px-5 py-3.5 font-semibold text-neutral-500 text-xs uppercase tracking-wider hidden md:table-cell">Contacto</th>
          <th class="text-center px-5 py-3.5 font-semibold text-neutral-500 text-xs uppercase tracking-wider hidden sm:table-cell">Cursos</th>
          <th class="text-center px-5 py-3.5 font-semibold text-neutral-500 text-xs uppercase tracking-wider hidden sm:table-cell">Aprobadas</th>
          <th class="text-center px-5 py-3.5 font-semibold text-neutral-500 text-xs uppercase tracking-wider">Estado</th>
          <th class="text-right px-5 py-3.5 font-semibold text-neutral-500 text-xs uppercase tracking-wider">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-neutral-100" id="tabla-alumnos">
        <?php foreach ($students as $s):
          $iniciales = strtoupper(mb_substr($s['first_name'] ?? '?', 0, 1) . mb_substr($s['last_name'] ?? '', 0, 1));
          $avatarColor = ['bg-primary-500', 'bg-emerald-500', 'bg-violet-500', 'bg-rose-500', 'bg-amber-500'][((int)$s['id']) % 5];
        ?>
          <tr class="hover:bg-primary-50/40 transition-colors <?= !$s['is_active'] ? 'opacity-50' : '' ?>" data-estado="<?= $s['is_active'] ? 'activos' : 'inactivos' ?>" data-cursos="<?= $s['enrolled_courses'] > 0 ? '1' : '0' ?>">
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 <?= $avatarColor ?> rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"><?= $iniciales ?></div>
                <div class="min-w-0">
                  <p class="font-semibold text-neutral-900 truncate"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></p>
                  <p class="text-xs text-neutral-400 truncate"><?= htmlspecialchars($s['rut'] ?? '') ?: '—' ?></p>
                </div>
              </div>
            </td>
            <td class="px-5 py-3.5 text-neutral-500 hidden md:table-cell">
              <p class="truncate max-w-[220px]"><?= htmlspecialchars($s['email']) ?></p>
            </td>
            <td class="px-5 py-3.5 text-center hidden sm:table-cell">
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?= $s['enrolled_courses'] > 0 ? 'bg-primary-50 text-primary-600' : 'bg-neutral-100 text-neutral-400' ?>">
                <?= $s['enrolled_courses'] ?>
              </span>
            </td>
            <td class="px-5 py-3.5 text-center hidden sm:table-cell">
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?= $s['passed_evals'] > 0 ? 'bg-green-50 text-green-600' : 'bg-neutral-100 text-neutral-400' ?>">
                <?= $s['passed_evals'] ?>
              </span>
            </td>
            <td class="px-5 py-3.5 text-center">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $s['is_active'] ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-500' ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $s['is_active'] ? 'bg-green-500' : 'bg-red-400' ?>"></span>
                <?= $s['is_active'] ? 'Activo' : 'Inactivo' ?>
              </span>
            </td>
            <td class="px-5 py-3.5 text-right">
              <div class="flex items-center justify-end gap-1">
                <button onclick="editStudent(<?= htmlspecialchars(json_encode($s)) ?>)" class="text-primary-600 hover:text-primary-800 hover:bg-primary-50 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors">Editar</button>
                <?php if ($s['is_active']): ?>
                  <form method="POST" onsubmit="return confirm('¿Desactivar a <?= htmlspecialchars($s['first_name']) ?>?')" class="inline">
                    <input type="hidden" name="action" value="deactivate_student">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <button type="submit" class="text-red-400 hover:text-red-600 hover:bg-red-50 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors">Desactivar</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (empty($students)): ?>
    <p class="text-center text-neutral-400 py-12">No hay alumnos todavía.</p>
  <?php endif; ?>
  <div id="sin-resultados" class="hidden text-center text-neutral-400 py-12">No se encontraron alumnos con ese filtro.</div>
</div>

<!-- Modal crear -->
<div id="createModal" class="hidden fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-lg font-bold text-neutral-900">Nuevo alumno</h3>
      <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-neutral-400 hover:text-neutral-600">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="action" value="create_student">
      <div class="grid grid-cols-2 gap-3">
        <input type="text" name="first_name" placeholder="Nombre" required
               class="w-full px-4 py-2.5 bg-neutral-50 border border-neutral-200 rounded-xl text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500">
        <input type="text" name="last_name" placeholder="Apellido" required
               class="w-full px-4 py-2.5 bg-neutral-50 border border-neutral-200 rounded-xl text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500">
      </div>
      <input type="email" name="email" placeholder="Correo electrónico" required
             class="w-full px-4 py-2.5 bg-neutral-50 border border-neutral-200 rounded-xl text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500">
      <input type="text" name="rut" placeholder="R.U.T. (XX.XXX.XXX-X)"
             class="w-full px-4 py-2.5 bg-neutral-50 border border-neutral-200 rounded-xl text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500 font-mono text-sm">
      <div>
        <input type="text" name="password" value="Selcap2026*" placeholder="Contraseña"
               class="w-full px-4 py-2.5 bg-neutral-50 border border-neutral-200 rounded-xl text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm">
        <p class="text-xs text-neutral-400 mt-1">Contraseña por defecto. El alumno puede cambiarla en Perfil.</p>
      </div>
      <div class="flex gap-2 pt-2">
        <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm">Crear alumno</button>
        <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="flex-1 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal editar -->
<div id="editModal" class="hidden fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-lg font-bold text-neutral-900">Editar alumno</h3>
      <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-neutral-400 hover:text-neutral-600">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="action" value="update_student">
      <input type="hidden" name="id" id="edit_id">
      <div class="grid grid-cols-2 gap-3">
        <input type="text" name="first_name" id="edit_first_name" placeholder="Nombre" required
               class="w-full px-4 py-2.5 bg-neutral-50 border border-neutral-200 rounded-xl text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500">
        <input type="text" name="last_name" id="edit_last_name" placeholder="Apellido" required
               class="w-full px-4 py-2.5 bg-neutral-50 border border-neutral-200 rounded-xl text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500">
      </div>
      <input type="email" name="email" id="edit_email" placeholder="Email" required
             class="w-full px-4 py-2.5 bg-neutral-50 border border-neutral-200 rounded-xl text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500">
      <input type="text" name="rut" id="edit_rut" placeholder="R.U.T. (XX.XXX.XXX-X)"
             class="w-full px-4 py-2.5 bg-neutral-50 border border-neutral-200 rounded-xl text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500 font-mono text-sm">
      <input type="text" name="new_password" id="edit_password" placeholder="Nueva contraseña (dejar vacío para no cambiar)"
             class="w-full px-4 py-2.5 bg-neutral-50 border border-neutral-200 rounded-xl text-neutral-900 focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm">
      <label class="flex items-center gap-2 text-sm text-neutral-700 cursor-pointer select-none">
        <input type="checkbox" name="is_active" id="edit_active" class="accent-primary-600 w-4 h-4">
        Activo
      </label>
      <div class="flex gap-2 pt-2">
        <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm">Guardar</button>
        <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="flex-1 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal CSV -->
<div id="bulkModal" class="hidden fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-bold text-neutral-900">📥 Cargar alumnos desde archivo</h3>
      <button onclick="document.getElementById('bulkModal').classList.add('hidden')" class="text-neutral-400 hover:text-neutral-600">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <p class="text-xs text-neutral-400 mb-4">CSV con una línea por alumno: <code class="bg-neutral-100 px-1.5 py-0.5 rounded">nombre, apellido, email, rut(opcional)</code>. Acepta <code class="bg-neutral-100 px-1.5 py-0.5 rounded">,</code> o <code class="bg-neutral-100 px-1.5 py-0.5 rounded">;</code> y encabezados opcionales. Contraseña inicial: <code class="bg-neutral-100 px-1.5 py-0.5 rounded">Selcap2026*</code>.</p>
    <form method="POST" enctype="multipart/form-data" class="space-y-3">
      <input type="hidden" name="action" value="create_students_bulk">
      <input type="file" name="csv_file" accept=".csv,text/csv" required
             class="w-full text-sm text-neutral-600 file:mr-3 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-600 hover:file:bg-primary-100">
      <div class="flex gap-2 pt-1">
        <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm">Cargar CSV</button>
        <a href="<?= BASE_URL ?>/admin/plantilla-alumnos.csv" download class="flex-1 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm text-center">⬇ Plantilla</a>
      </div>
    </form>
  </div>
</div>

<script>
let filtroActual = 'todos';

function setFilter(f) {
  filtroActual = f;
  document.querySelectorAll('.filter-btn').forEach(b => {
    const active = b.dataset.filter === f;
    b.className = 'filter-btn px-3.5 py-2 rounded-xl font-semibold transition-colors ' +
      (active ? 'bg-primary-600 text-white' : 'text-neutral-600 hover:bg-neutral-100');
  });
  aplicarFiltros();
}

function aplicarFiltros() {
  const q = (document.getElementById('buscar').value || '').toLowerCase();
  let visibles = 0;
  document.querySelectorAll('#tabla-alumnos tr').forEach(tr => {
    const texto = tr.textContent.toLowerCase();
    const estado = tr.dataset.estado;
    const conCursos = tr.dataset.cursos === '1';
    let ok = true;
    if (filtroActual === 'activos' && estado !== 'activos') ok = false;
    if (filtroActual === 'inactivos' && estado !== 'inactivos') ok = false;
    if (filtroActual === 'conCursos' && !conCursos) ok = false;
    if (q && !texto.includes(q)) ok = false;
    tr.style.display = ok ? '' : 'none';
    if (ok) visibles++;
  });
  document.getElementById('sin-resultados').classList.toggle('hidden', visibles > 0);
}

document.getElementById('buscar').addEventListener('input', aplicarFiltros);

function openCreate() { document.getElementById('createModal').classList.remove('hidden'); }
function openBulk() { document.getElementById('bulkModal').classList.remove('hidden'); }

function editStudent(s) {
  document.getElementById('edit_id').value = s.id;
  document.getElementById('edit_first_name').value = s.first_name;
  document.getElementById('edit_last_name').value = s.last_name;
  document.getElementById('edit_email').value = s.email;
  document.getElementById('edit_rut').value = s.rut || '';
  document.getElementById('edit_password').value = '';
  document.getElementById('edit_active').checked = s.is_active == 1;
  document.getElementById('editModal').classList.remove('hidden');
}

// Cerrar modales con click afuera
['createModal', 'editModal', 'bulkModal'].forEach(id => {
  document.getElementById(id).addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
  });
});
// Cerrar con Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') ['createModal', 'editModal', 'bulkModal'].forEach(id => document.getElementById(id).classList.add('hidden'));
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
