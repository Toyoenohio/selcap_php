<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/webhooks.php';
requireAdmin();

$pdo = db();
$msg = ''; $msgType = '';

// ── Acción masiva ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $courseId = (int)$_POST['course_id'];

    if ($_POST['action'] === 'enroll_bulk' && !empty($_POST['user_ids'])) {
        $userIds = array_map('intval', $_POST['user_ids']);
        $count = 0;
        $stmt = $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)');
        foreach ($userIds as $uid) {
            $stmt->execute([$uid, $courseId]);
            $rowCount = $stmt->rowCount();
            $count += $rowCount;
            // Webhook de bienvenida solo para alumnos nuevos en el curso
            if ($rowCount > 0) {
                notifyEnrollmentToWebhook($uid, $courseId, 'bulk');
            }
        }
        $msg = "$count alumno(s) matriculado(s).";
        $msgType = 'green';

    } elseif ($_POST['action'] === 'unenroll_bulk' && !empty($_POST['user_ids'])) {
        $userIds = array_map('intval', $_POST['user_ids']);
        $stmt = $pdo->prepare('DELETE FROM enrollments WHERE user_id = ? AND course_id = ?');
        $count = 0;
        foreach ($userIds as $uid) {
            $stmt->execute([$uid, $courseId]);
            $count += $stmt->rowCount();
        }
        $msg = "$count alumno(s) desmatriculado(s).";
        $msgType = 'red';
    }
}

// ── Datos ──
$coursesStmt = $pdo->query("SELECT id, title, status FROM courses ORDER BY title");
$courses = $coursesStmt->fetchAll();

$selectedCourse = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

$enrolledIds = [];
if ($selectedCourse) {
    $enrStmt = $pdo->prepare("SELECT user_id FROM enrollments WHERE course_id = ?");
    $enrStmt->execute([$selectedCourse]);
    $enrolledIds = $enrStmt->fetchAll(PDO::FETCH_COLUMN);
}

$studentsStmt = $pdo->query("SELECT id, first_name, last_name, email, rut, is_active FROM users WHERE role = 'student' ORDER BY first_name");
$students = $studentsStmt->fetchAll();

// ── Header ──
require __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-extrabold text-gray-900">Matrícula Masiva</h1>
      <p class="text-gray-500 text-sm mt-1">Asigna múltiples alumnos a un curso en una sola operación.</p>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium <?= $msgType === 'green' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <!-- ── Selector de curso ── -->
  <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
    <label class="block text-sm font-semibold text-gray-700 mb-2">📚 Seleccionar curso</label>
    <div class="flex gap-3">
      <select name="course_id" onchange="this.form.submit()" class="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">— Elegir curso —</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $selectedCourse === (int)$c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['title']) ?> <?= $c['status'] === 'draft' ? '(borrador)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors text-sm">
        Cargar
      </button>
    </div>
  </form>

  <?php if ($selectedCourse): ?>
    <?php
      $notEnrolled = array_filter($students, fn($s) => !in_array($s['id'], $enrolledIds));
      $enrolledStudents = array_filter($students, fn($s) => in_array($s['id'], $enrolledIds));
    ?>

    <!-- ── No matriculados ── -->
    <form method="POST" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
      <input type="hidden" name="course_id" value="<?= $selectedCourse ?>">
      <input type="hidden" name="action" value="enroll_bulk">

      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <div>
          <h2 class="font-bold text-gray-900">Alumnos sin matricular</h2>
          <p class="text-xs text-gray-500"><?= count($notEnrolled) ?> disponible(s)</p>
        </div>
        <div class="flex gap-2">
          <button type="button" onclick="toggleAll('enroll', true)" class="text-xs text-blue-600 hover:underline font-semibold">Seleccionar todos</button>
          <button type="button" onclick="toggleAll('enroll', false)" class="text-xs text-gray-500 hover:underline">Deseleccionar</button>
        </div>
      </div>

      <?php if (empty($notEnrolled)): ?>
        <div class="p-8 text-center text-gray-400 text-sm">Todos los alumnos ya están matriculados en este curso.</div>
      <?php else: ?>
        <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
          <?php foreach ($notEnrolled as $s): ?>
            <label class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 cursor-pointer transition-colors">
              <input type="checkbox" name="user_ids[]" value="<?= $s['id'] ?>" class="enroll-check w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></p>
                <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($s['email']) ?> <?= $s['rut'] ? '· ' . htmlspecialchars($s['rut']) : '' ?></p>
              </div>
              <?php if (!$s['is_active']): ?>
                <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-medium">Inactivo</span>
              <?php endif; ?>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="px-5 py-4 border-t border-gray-100 bg-gray-50/30">
          <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">
            ✅ Matricular seleccionados
          </button>
        </div>
      <?php endif; ?>
    </form>

    <!-- ── Ya matriculados ── -->
    <?php if (!empty($enrolledStudents)): ?>
    <form method="POST" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
      <input type="hidden" name="course_id" value="<?= $selectedCourse ?>">
      <input type="hidden" name="action" value="unenroll_bulk">

      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <div>
          <h2 class="font-bold text-gray-900">Alumnos matriculados</h2>
          <p class="text-xs text-gray-500"><?= count($enrolledStudents) ?> alumno(s)</p>
        </div>
        <div class="flex gap-2">
          <button type="button" onclick="toggleAll('unenroll', true)" class="text-xs text-red-600 hover:underline font-semibold">Seleccionar todos</button>
          <button type="button" onclick="toggleAll('unenroll', false)" class="text-xs text-gray-500 hover:underline">Deseleccionar</button>
        </div>
      </div>

      <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
        <?php foreach ($enrolledStudents as $s): ?>
          <label class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 cursor-pointer transition-colors">
            <input type="checkbox" name="user_ids[]" value="<?= $s['id'] ?>" class="unenroll-check w-4 h-4 rounded border-gray-300 text-red-500 focus:ring-red-500">
            <div class="flex-1 min-w-0">
              <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></p>
              <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($s['email']) ?> <?= $s['rut'] ? '· ' . htmlspecialchars($s['rut']) : '' ?></p>
            </div>
            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Matriculado</span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="px-5 py-4 border-t border-gray-100 bg-gray-50/30">
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm"
                onclick="return confirm('¿Desmatricular a los alumnos seleccionados?')">
          ❌ Desmatricular seleccionados
        </button>
      </div>
    </form>
    <?php endif; ?>

  <?php endif; ?>
</div>

<script>
function toggleAll(prefix, checked) {
  document.querySelectorAll('.' + prefix + '-check').forEach(cb => { cb.checked = checked; });
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
