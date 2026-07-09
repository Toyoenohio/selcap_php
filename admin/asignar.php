<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pdo = db();
$msg = ''; $msgType = '';

// ── Acciones ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'enroll') {
        $userId = (int)$_POST['user_id'];
        $courseId = (int)$_POST['course_id'];
        try {
            $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)')->execute([$userId, $courseId]);
            $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$_SESSION['user_id'], 'student_enrolled', 'enrollment', $courseId,
                    json_encode(['user_id' => $userId]), $_SERVER['REMOTE_ADDR'] ?? '']);
            $msg = 'Alumno matriculado.'; $msgType = 'green';
        } catch (PDOException $e) {
            $msg = 'Error: ' . $e->getMessage(); $msgType = 'red';
        }

    } elseif ($_POST['action'] === 'unenroll') {
        $userId = (int)$_POST['user_id'];
        $courseId = (int)$_POST['course_id'];
        $pdo->prepare('DELETE FROM enrollments WHERE user_id=? AND course_id=?')->execute([$userId, $courseId]);
        $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$_SESSION['user_id'], 'student_unenrolled', 'enrollment', $courseId,
                json_encode(['user_id' => $userId]), $_SERVER['REMOTE_ADDR'] ?? '']);
        $msg = 'Alumno desmatriculado.'; $msgType = 'red';
    }
}

// Datos
$selectedStudent = $_GET['student_id'] ?? null;

// Estudiantes
$studentsStmt = $pdo->query("SELECT id, first_name, last_name, email, is_active FROM users WHERE role='student' ORDER BY first_name");
$students = $studentsStmt->fetchAll();

// Cursos
$coursesStmt = $pdo->query("SELECT id, title, status FROM courses ORDER BY title");
$courses = $coursesStmt->fetchAll();

// Matrículas actuales del estudiante seleccionado
$enrollments = [];
if ($selectedStudent) {
    $enrStmt = $pdo->prepare("SELECT c.id, c.title, c.status, e.enrolled_at 
        FROM enrollments e JOIN courses c ON e.course_id=c.id 
        WHERE e.user_id=? ORDER BY e.enrolled_at DESC");
    $enrStmt->execute([$selectedStudent]);
    $enrollments = $enrStmt->fetchAll();

    $studentInfo = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id=?");
    $studentInfo->execute([$selectedStudent]);
    $studentInfo = $studentInfo->fetch();
}

$pageTitle = 'Admin — Asignar Cursos';
$currentPage = 'asignar';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-extrabold text-gray-900">Asignar Alumnos a Cursos</h1>
  <div class="flex items-center gap-2 text-sm flex-wrap">
    <a href="<?= BASE_URL ?>/admin/courses.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Cursos</a>
    <a href="<?= BASE_URL ?>/admin/alumnos.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Alumnos</a>
    <a href="<?= BASE_URL ?>/admin/asignar.php" class="bg-selcap-600 text-white px-4 py-2 rounded-xl font-semibold">Asignar</a>
    <a href="<?= BASE_URL ?>/admin/reportes.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Reportes</a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 px-4 py-3 rounded-xl text-sm mb-4"><?= $msg ?></div>
<?php endif; ?>

<!-- Buscar estudiante -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
  <h2 class="font-bold text-gray-800 mb-3">Seleccionar alumno</h2>
  <form method="GET" class="flex gap-2">
    <select name="student_id" class="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
      <option value="">— Buscar alumno —</option>
      <?php foreach ($students as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $selectedStudent == $s['id'] ? 'selected' : '' ?>
          class="<?= !$s['is_active'] ? 'text-gray-400' : '' ?>">
          <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?> — <?= htmlspecialchars($s['email']) ?>
          <?= !$s['is_active'] ? '(Inactivo)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-6 py-2.5 rounded-xl transition-colors text-sm">
      Ver cursos
    </button>
  </form>
</div>

<?php if ($selectedStudent && $studentInfo): ?>
<!-- Info del alumno -->
<div class="bg-selcap-50 border border-selcap-200 rounded-2xl p-5 mb-6">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-full bg-selcap-600 text-white flex items-center justify-center font-bold text-sm">
      <?= strtoupper(substr($studentInfo['first_name'], 0, 1) . substr($studentInfo['last_name'], 0, 1)) ?>
    </div>
    <div>
      <p class="font-bold text-gray-900"><?= htmlspecialchars($studentInfo['first_name'] . ' ' . $studentInfo['last_name']) ?></p>
      <p class="text-sm text-gray-500"><?= htmlspecialchars($studentInfo['email']) ?></p>
    </div>
  </div>
</div>

<div class="grid lg:grid-cols-2 gap-6">
  <!-- Asignar nuevo curso -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
    <h3 class="font-bold text-gray-800 mb-4">Matricular en un curso</h3>
    <form method="POST" class="flex gap-2">
      <input type="hidden" name="action" value="enroll">
      <input type="hidden" name="user_id" value="<?= $selectedStudent ?>">
      <select name="course_id" required class="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
        <option value="">— Seleccionar curso —</option>
        <?php foreach ($courses as $c): ?>
          <?php 
            $alreadyEnrolled = array_filter($enrollments, fn($e) => $e['id'] == $c['id']);
            if (empty($alreadyEnrolled)):
          ?>
            <option value="<?= $c['id'] ?>">
              <?= htmlspecialchars($c['title']) ?> 
              <?= $c['status'] === 'draft' ? '(Borrador)' : ($c['status'] === 'archived' ? '(Archivado)' : '') ?>
            </option>
          <?php endif; ?>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm flex-shrink-0">
        Matricular
      </button>
    </form>
  </div>

  <!-- Cursos actuales -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
    <h3 class="font-bold text-gray-800 mb-4">Cursos matriculados (<?= count($enrollments) ?>)</h3>
    <?php if (empty($enrollments)): ?>
      <p class="text-gray-400 text-sm">No está matriculado en ningún curso.</p>
    <?php else: ?>
      <div class="space-y-2">
        <?php foreach ($enrollments as $enr): ?>
          <div class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-3">
            <div>
              <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($enr['title']) ?></p>
              <p class="text-xs text-gray-400"><?= date('d/m/Y', strtotime($enr['enrolled_at'])) ?></p>
            </div>
            <form method="POST" onsubmit="return confirm('¿Desmatricular de <?= htmlspecialchars(addslashes($enr['title'])) ?>?')">
              <input type="hidden" name="action" value="unenroll">
              <input type="hidden" name="user_id" value="<?= $selectedStudent ?>">
              <input type="hidden" name="course_id" value="<?= $enr['id'] ?>">
              <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold">Desmatricular</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($selectedStudent): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center text-gray-400">
    Alumno no encontrado.
  </div>
<?php else: ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center text-gray-400">
    Seleccioná un alumno para gestionar sus cursos.
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
