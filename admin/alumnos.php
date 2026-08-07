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

// ── Lista de alumnos ──
$filter = $_GET['search'] ?? '';
if ($filter) {
    $stmt = $pdo->prepare("SELECT u.*, 
        (SELECT COUNT(*) FROM enrollments e WHERE e.user_id = u.id) as enrolled_courses,
        (SELECT COUNT(*) FROM evaluation_attempts ea WHERE ea.user_id = u.id AND ea.passed = 1) as passed_evals
        FROM users u WHERE u.role = 'student' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?) ORDER BY u.created_at DESC");
    $like = "%$filter%";
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->prepare("SELECT u.*, 
        (SELECT COUNT(*) FROM enrollments e WHERE e.user_id = u.id) as enrolled_courses,
        (SELECT COUNT(*) FROM evaluation_attempts ea WHERE ea.user_id = u.id AND ea.passed = 1) as passed_evals
        FROM users u WHERE u.role = 'student' ORDER BY u.created_at DESC LIMIT 50");
    $stmt->execute();
}
$students = $stmt->fetchAll();

$pageTitle = 'Admin — Alumnos';
$currentPage = 'alumnos';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-extrabold text-gray-900">Alumnos</h1>
  <div class="flex items-center gap-2 text-sm flex-wrap">
    <a href="<?= BASE_URL ?>/admin/courses.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Cursos</a>
    <a href="<?= BASE_URL ?>/admin/alumnos.php" class="bg-selcap-600 text-white px-4 py-2 rounded-xl font-semibold">Alumnos</a>
    <a href="<?= BASE_URL ?>/admin/asignar.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Asignar</a>
    <a href="<?= BASE_URL ?>/admin/reportes.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Reportes</a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 px-4 py-3 rounded-xl text-sm mb-4"><?= $msg ?></div>
<?php endif; ?>

<!-- Crear alumno -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
  <h2 class="font-bold text-gray-800 mb-3">Crear nuevo alumno</h2>
  <form method="POST" class="space-y-3">
    <input type="hidden" name="action" value="create_student">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <input type="text" name="first_name" placeholder="Nombre" required
             class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
      <input type="text" name="last_name" placeholder="Apellido" required
             class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <input type="email" name="email" placeholder="Correo electrónico" required
             class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
      <input type="text" name="rut" placeholder="R.U.T. (XX.XXX.XXX-X)"
             class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-mono text-sm">
    </div>
    <div>
        <input type="text" name="password" value="Selcap2026*" placeholder="Contraseña"
               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
        <p class="text-xs text-gray-400 mt-1">Contraseña por defecto. El alumno puede cambiarla en Perfil.</p>
      </div>
    </div>
    <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">
      Crear alumno
    </button>
  </form>
</div>

<!-- Carga masiva -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
  <h2 class="font-bold text-gray-800 mb-1">📥 Cargar alumnos desde archivo</h2>
  <p class="text-xs text-gray-400 mb-3">Subí un <strong>CSV</strong> con una línea por alumno: <code class="bg-gray-100 px-1 rounded">nombre, apellido, email, rut(opcional)</code>. Acepta separador <code class="bg-gray-100 px-1 rounded">,</code> o <code class="bg-gray-100 px-1 rounded">;</code> y fila de encabezados opcional. La contraseña inicial de todos es <code class="bg-gray-100 px-1 rounded">Selcap2026*</code>.</p>
  <form method="POST" enctype="multipart/form-data" class="flex items-end gap-3 flex-wrap">
    <input type="hidden" name="action" value="create_students_bulk">
    <input type="file" name="csv_file" accept=".csv,text/csv" required
           class="text-sm text-gray-600 file:mr-3 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-selcap-50 file:text-selcap-700 hover:file:bg-selcap-100">
    <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">Cargar CSV</button>
    <a href="<?= BASE_URL ?>/admin/plantilla-alumnos.csv" download class="text-selcap-600 hover:text-selcap-700 text-sm font-medium">⬇ Descargar plantilla</a>
  </form>
  <?php if (!empty($bulkErrors)): ?>
    <details class="mt-3">
      <summary class="text-red-500 text-xs font-semibold cursor-pointer">Ver <?= count($bulkErrors) ?> detalle(s) de error</summary>
      <div class="mt-2 bg-red-50 border border-red-100 rounded-xl p-3 text-xs text-red-700 space-y-1 max-h-40 overflow-y-auto">
        <?php foreach ($bulkErrors as $e): ?><p><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>
</div>

<!-- Buscar -->
<form method="GET" class="mb-4">
  <div class="flex gap-2">
    <input type="text" name="search" value="<?= htmlspecialchars($filter) ?>" placeholder="Buscar por nombre o email..."
           class="flex-1 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
    <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm">Buscar</button>
    <?php if ($filter): ?>
      <a href="<?= BASE_URL ?>/admin/alumnos.php" class="bg-gray-50 hover:bg-gray-100 text-gray-500 font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm">Limpiar</a>
    <?php endif; ?>
  </div>
</form>

<!-- Tabla de alumnos -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="text-left px-4 py-3 font-semibold text-gray-600">Nombre</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden sm:table-cell">Email</th>
          <th class="text-center px-4 py-3 font-semibold text-gray-600 hidden sm:table-cell">Cursos</th>
          <th class="text-center px-4 py-3 font-semibold text-gray-600 hidden sm:table-cell">Aprobadas</th>
          <th class="text-center px-4 py-3 font-semibold text-gray-600">Estado</th>
          <th class="text-right px-4 py-3 font-semibold text-gray-600">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php foreach ($students as $s): ?>
          <tr class="hover:bg-gray-50 transition-colors <?= !$s['is_active'] ? 'opacity-50' : '' ?>">
            <td class="px-4 py-3 font-medium text-gray-900">
              <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
            </td>
            <td class="px-4 py-3 text-gray-500 hidden sm:table-cell"><?= htmlspecialchars($s['email']) ?></td>
            <td class="px-4 py-3 text-center text-gray-500 hidden sm:table-cell"><?= $s['enrolled_courses'] ?></td>
            <td class="px-4 py-3 text-center hidden sm:table-cell">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $s['passed_evals'] > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                <?= $s['passed_evals'] ?>
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $s['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= $s['is_active'] ? 'Activo' : 'Inactivo' ?>
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button onclick="editStudent(<?= htmlspecialchars(json_encode($s)) ?>)" class="text-blue-600 hover:text-blue-800 text-xs font-semibold px-2 py-1">Editar</button>
                <?php if ($s['is_active']): ?>
                  <form method="POST" onsubmit="return confirm('¿Desactivar a <?= htmlspecialchars($s['first_name']) ?>?')" class="inline">
                    <input type="hidden" name="action" value="deactivate_student">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold px-2 py-1">Desactivar</button>
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
    <p class="text-center text-gray-400 py-12">No se encontraron alumnos.</p>
  <?php endif; ?>
</div>

<!-- Modal editar (oculto) -->
<div id="editModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-4">Editar alumno</h3>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="action" value="update_student">
      <input type="hidden" name="id" id="edit_id">
      <div class="grid grid-cols-2 gap-3">
        <input type="text" name="first_name" id="edit_first_name" placeholder="Nombre" required
               class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
        <input type="text" name="last_name" id="edit_last_name" placeholder="Apellido" required
               class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
      </div>
      <input type="email" name="email" id="edit_email" placeholder="Email" required
             class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
      <input type="text" name="rut" id="edit_rut" placeholder="R.U.T. (XX.XXX.XXX-X)"
             class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-mono text-sm">
      <input type="text" name="new_password" id="edit_password" placeholder="Nueva contraseña (dejar vacío para no cambiar)"
             class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
      <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="is_active" id="edit_active" class="accent-selcap-600 w-4 h-4">
        Activo
      </label>
      <div class="flex gap-2 pt-2">
        <button type="submit" class="flex-1 bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm">Guardar</button>
        <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
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
// Click outside to close
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
